<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Actions\GenerateNarrativesForReport;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class GenerateNarrativesForReportTest extends TestCase
{
    use RefreshDatabase;

    private MockLlmProvider $mockLlm;

    private GenerateNarrativesForReport $action;

    public function test_creates_narratives_for_tasks(): void
    {
        $report = Report::factory()->draft()->create();
        ReportTask::factory()->count(2)->create(['report_id' => $report->id]);

        ($this->action)($report);

        $report->reportTasks->each(function (ReportTask $reportTask): void {
            $reportTask->refresh();
            $this->assertEquals($this->mockLlm->narrativeText, $reportTask->narrative);
        });
    }

    public function test_handles_llm_failure(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $reportTask->refresh();
        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $reportTask->narrative
        );
    }

    public function test_generates_day_fallback(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $this->assertCount(1, $this->mockLlm->fallbackRequests);
    }

    public function test_skips_day_fallback_for_non_bitrix24_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromCommits()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $this->assertCount(0, $this->mockLlm->fallbackRequests);
    }

    public function test_saves_fallback_narrative_on_day(): void
    {
        $report = Report::factory()->draft()->create();
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $reportDay->refresh();
        $this->assertEquals($this->mockLlm->fallbackText, $reportDay->narrative);
    }

    public function test_updates_status_to_generated(): void
    {
        $report = Report::factory()->draft()->create();

        ($this->action)($report);

        $report->refresh();
        $this->assertEquals(ReportStatus::Generated, $report->status);
    }

    public function test_calls_llm_once_per_task(): void
    {
        $report = Report::factory()->draft()->create();
        ReportTask::factory()->count(3)->create(['report_id' => $report->id]);

        ($this->action)($report);

        $this->assertCount(3, $this->mockLlm->narrativeRequests);
    }

    public function test_day_fallback_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $reportDay->refresh();
        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $reportDay->narrative
        );
    }

    public function test_does_not_call_fallback_for_commits_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromCommits()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $this->assertEmpty($this->mockLlm->fallbackRequests);
    }

    public function test_does_not_call_fallback_for_manual_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->manuallyEdited()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $this->assertEmpty($this->mockLlm->fallbackRequests);
    }

    public function test_status_is_generated_even_when_llm_fails(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        ReportTask::factory()->create(['report_id' => $report->id]);

        ($this->action)($report);

        $report->refresh();
        $this->assertEquals(ReportStatus::Generated, $report->status);
    }

    public function test_day_task_first_day_has_empty_previous_narratives(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);
        $reportDay = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        ReportDayTask::factory()->create(['report_day_id' => $reportDay->id, 'report_task_id' => $reportTask->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-11 10:00:00']);

        ($this->action)($report);

        $this->assertGreaterThanOrEqual(1, count($this->mockLlm->narrativeRequests));
        $this->assertSame([], $this->mockLlm->narrativeRequests[0]->previousNarratives);
    }

    public function test_day_task_second_day_receives_first_day_narrative(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $day1 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $day2 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-12']);
        ReportDayTask::factory()->create(['report_day_id' => $day1->id, 'report_task_id' => $reportTask->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day2->id, 'report_task_id' => $reportTask->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-11 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-12 10:00:00']);

        ($this->action)($report);

        $dayTaskRequests = $this->dayTaskRequests();
        $this->assertGreaterThanOrEqual(2, count($dayTaskRequests));
        $this->assertSame([], $dayTaskRequests[0]->previousNarratives);
        $this->assertSame([$this->mockLlm->narrativeText], $dayTaskRequests[1]->previousNarratives);
    }

    public function test_day_task_third_day_receives_two_previous_narratives(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $day1 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $day2 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-12']);
        $day3 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-13']);
        ReportDayTask::factory()->create(['report_day_id' => $day1->id, 'report_task_id' => $reportTask->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day2->id, 'report_task_id' => $reportTask->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day3->id, 'report_task_id' => $reportTask->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-11 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-12 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-13 10:00:00']);

        ($this->action)($report);

        $dayTaskRequests = $this->dayTaskRequests();
        $this->assertCount(3, $dayTaskRequests);
        $this->assertCount(2, $dayTaskRequests[2]->previousNarratives);
    }

    public function test_different_tasks_do_not_share_previous_narratives(): void
    {
        $report = Report::factory()->draft()->create();

        $task1 = Task::factory()->create();
        $branch1 = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch1->id, 'task_id' => $task1->id]);
        $reportTask1 = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task1->id]);

        $task2 = Task::factory()->create();
        $branch2 = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch2->id, 'task_id' => $task2->id]);
        $reportTask2 = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task2->id]);

        $day1 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $day2 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-12']);
        ReportDayTask::factory()->create(['report_day_id' => $day1->id, 'report_task_id' => $reportTask1->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day2->id, 'report_task_id' => $reportTask2->id]);
        Commit::factory()->create(['branch_id' => $branch1->id, 'committed_at' => '2024-03-11 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch2->id, 'committed_at' => '2024-03-12 10:00:00']);

        ($this->action)($report);

        $this->assertGreaterThanOrEqual(2, count($this->mockLlm->narrativeRequests));
        $this->assertSame([], $this->mockLlm->narrativeRequests[0]->previousNarratives);
        $this->assertSame([], $this->mockLlm->narrativeRequests[1]->previousNarratives);
    }

    public function test_placeholder_not_accumulated_as_previous_narrative(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);

        $placeholder = '[Не удалось сгенерировать описание. Отредактируйте вручную.]';
        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => $placeholder,
        ]);

        $day1 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $day2 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-12']);
        ReportDayTask::factory()->create(['report_day_id' => $day1->id, 'report_task_id' => $reportTask->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day2->id, 'report_task_id' => $reportTask->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-11 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-12 10:00:00']);

        ($this->action)($report);

        $this->assertGreaterThanOrEqual(2, count($this->mockLlm->narrativeRequests));
        $this->assertSame([], $this->mockLlm->narrativeRequests[1]->previousNarratives);
    }

    /**
     * When a day-task has no commits of its own, it must fall back to the global narrative.
     * After the order fix (global → day-task → day-level), global has already been generated
     * when fallback runs, so day-task gets the LLM-generated global text.
     * With the current wrong order (day-task → day-level → global), fallback runs before
     * global, copying whatever value the reportTask had before generation (null or DB preset).
     *
     * Fixture: reportTask is created with narrative=null; no commits on this specific day
     * so the code triggers fallbackToGlobalNarrative() immediately.
     *
     * TDD red test — fails until the execution order is fixed to global → day-task.
     */
    public function test_global_task_narrative_is_generated_before_day_task_narratives(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);

        // Start with null narrative so we can tell whether global ran before fallback was invoked
        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => null,
        ]);

        // Day has no commits at all — fallbackToGlobalNarrative() is triggered immediately
        $reportDay = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $reportDayTask = ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);
        // No Commit created — commits list is empty, fallback fires

        ($this->action)($report);

        $reportDayTask->refresh();

        // After the order fix: global ran first → narrativeText was set → fallback copies it
        // Before the fix: fallback ran first while reportTask.narrative was still null
        $this->assertSame(
            $this->mockLlm->narrativeText,
            $reportDayTask->narrative,
            'Day-task fallback must contain the global LLM narrative — proving global ran before day-task'
        );
    }

    /**
     * When a day-task has no commits, the fallback must copy the global narrative
     * that was generated earlier in the same invocation.
     *
     * Symmetrical to the order test above; kept as a focused scenario.
     * TDD red test — fails until the execution order is fixed.
     */
    public function test_day_task_fallback_uses_already_generated_global_narrative(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);

        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => null,
        ]);

        $reportDay = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $reportDayTask = ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);
        // No commits → fallbackToGlobalNarrative() is triggered

        ($this->action)($report);

        $reportDayTask->refresh();

        $this->assertSame(
            $this->mockLlm->narrativeText,
            $reportDayTask->narrative,
            'When day-task falls back, it must receive the global narrative that was already generated'
        );
    }

    /**
     * When global LLM generation fails (placeholder is set) and the day-task also has no commits,
     * the fallback must copy the placeholder — not null or any stale pre-DB value.
     *
     * With the current wrong order (day-task first), the fallback copies the pre-seeded factory
     * value (random paragraph), not the placeholder. Only after the order fix will the placeholder
     * be available when fallback runs.
     *
     * TDD red test — fails until the execution order is fixed.
     */
    public function test_day_task_fallback_uses_placeholder_when_global_also_failed(): void
    {
        $report = Report::factory()->draft()->create();
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->auto()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);

        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => null,
        ]);

        $reportDay = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $reportDayTask = ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);
        // No commits → fallbackToGlobalNarrative() is triggered

        // All LLM calls fail — global generation results in placeholder
        $this->mockLlm->shouldFail = true;

        ($this->action)($report);

        $reportDayTask->refresh();

        $this->assertSame(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $reportDayTask->narrative,
            'When global fails (placeholder) and day-task falls back, it must receive the placeholder — not null'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlm = new MockLlmProvider();
        $this->action = new GenerateNarrativesForReport($this->mockLlm, new NarrativeSupport());
    }

    /**
     * Returns only the day-task generateNarrative() calls in call order.
     * Filters by matching each narrativeRequests index against callOrder,
     * so results are resilient to changes in global/day-task execution order.
     *
     * @return list<\App\Domain\Narrative\DTOs\TaskNarrativeRequest>
     */
    private function dayTaskRequests(): array
    {
        return array_values(array_filter(
            $this->mockLlm->narrativeRequests,
            fn (mixed $_, int $i): bool => ($this->mockLlm->callOrder[$i] ?? null) === 'day-task',
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
