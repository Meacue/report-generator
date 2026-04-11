<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Narrative;

use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Models\NarrativeHistory;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Bitrix24\Models\Task;
use App\Services\Narrative\NarrativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class NarrativeServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockLlmProvider $mockLlm;

    private NarrativeService $service;

    public function test_generate_for_report_creates_narratives_for_tasks(): void
    {
        $report = Report::factory()->draft()->create();
        ReportTask::factory()->count(2)->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $report->reportTasks->each(function (ReportTask $reportTask): void {
            $reportTask->refresh();
            $this->assertEquals($this->mockLlm->narrativeText, $reportTask->narrative);
        });
    }

    public function test_generate_for_report_handles_llm_failure(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $reportTask->refresh();
        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $reportTask->narrative
        );
    }

    public function test_generate_for_report_generates_day_fallback(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $this->assertCount(1, $this->mockLlm->fallbackRequests);
    }

    public function test_generate_for_report_skips_day_fallback_for_non_bitrix24_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromCommits()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $this->assertCount(0, $this->mockLlm->fallbackRequests);
    }

    public function test_generate_for_report_saves_fallback_narrative_on_day(): void
    {
        $report = Report::factory()->draft()->create();
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $reportDay->refresh();
        $this->assertEquals($this->mockLlm->fallbackText, $reportDay->narrative);
    }

    public function test_generate_for_report_updates_status_to_generated(): void
    {
        $report = Report::factory()->draft()->create();

        $this->service->generateForReport($report);

        $report->refresh();
        $this->assertEquals(ReportStatus::Generated, $report->status);
    }

    public function test_regenerate_task_saves_history(): void
    {
        $previousNarrative = 'Previous narrative content.';
        $reportTask = ReportTask::factory()->create(['narrative' => $previousNarrative]);

        $this->service->regenerateTask($reportTask);

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::LlmRegeneration->value,
        ]);
    }

    public function test_regenerate_task_updates_narrative(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Old narrative.']);

        $result = $this->service->regenerateTask($reportTask);

        $this->assertEquals($this->mockLlm->narrativeText, $result->narrative);
    }

    public function test_regenerate_task_sets_is_edited_to_false(): void
    {
        $reportTask = ReportTask::factory()->edited()->create();

        $result = $this->service->regenerateTask($reportTask);

        $this->assertFalse($result->is_edited);
    }

    public function test_regenerate_day_saves_history(): void
    {
        $previousNarrative = 'Previous day narrative.';
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['narrative' => $previousNarrative]);

        $this->service->regenerateDay($reportDay);

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => 'report_day',
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $previousNarrative,
            'source'             => NarrativeSource::LlmRegeneration->value,
        ]);
    }

    public function test_regenerate_day_updates_narrative(): void
    {
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create();

        $result = $this->service->regenerateDay($reportDay);

        $this->assertEquals($this->mockLlm->fallbackText, $result->narrative);
    }

    public function test_regenerate_day_sets_is_edited_to_false(): void
    {
        $reportDay = ReportDay::factory()->manuallyEdited()->create();

        $result = $this->service->regenerateDay($reportDay);

        $this->assertFalse($result->is_edited);
    }

    public function test_edit_task_narrative_saves_history(): void
    {
        $oldNarrative = 'Original narrative text.';
        $reportTask = ReportTask::factory()->create(['narrative' => $oldNarrative]);

        $this->service->editTaskNarrative($reportTask, 'New manual narrative.');

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $oldNarrative,
            'source'             => NarrativeSource::ManualEdit->value,
        ]);
    }

    public function test_edit_task_narrative_sets_is_edited_to_true(): void
    {
        $reportTask = ReportTask::factory()->llmGenerated()->create();

        $result = $this->service->editTaskNarrative($reportTask, 'Manually written narrative.');

        $this->assertTrue($result->is_edited);
    }

    public function test_edit_task_narrative_updates_narrative_text(): void
    {
        $reportTask = ReportTask::factory()->create();
        $newText = 'Manually written narrative text.';

        $result = $this->service->editTaskNarrative($reportTask, $newText);

        $this->assertEquals($newText, $result->narrative);
    }

    public function test_edit_day_narrative_saves_history(): void
    {
        $oldNarrative = 'Original day narrative.';
        $reportDay = ReportDay::factory()->fromCommits()->create(['narrative' => $oldNarrative]);

        $this->service->editDayNarrative($reportDay, 'New manual day narrative.');

        $this->assertDatabaseHas('narrative_history', [
            'narratable_type'    => 'report_day',
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $oldNarrative,
            'source'             => NarrativeSource::ManualEdit->value,
        ]);
    }

    public function test_edit_day_narrative_sets_is_edited_to_true(): void
    {
        $reportDay = ReportDay::factory()->fromCommits()->create();

        $result = $this->service->editDayNarrative($reportDay, 'Manually written day narrative.');

        $this->assertTrue($result->is_edited);
    }

    public function test_undo_task_narrative_restores_previous(): void
    {
        $previousNarrative = 'This was the previous narrative.';
        $reportTask = ReportTask::factory()->create(['narrative' => 'Current narrative.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => $previousNarrative,
            'changed_at'         => now(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = $this->service->undoTaskNarrative($reportTask);

        $this->assertNotNull($result);
        $this->assertEquals($previousNarrative, $result->narrative);
    }

    public function test_undo_task_narrative_deletes_history_entry(): void
    {
        $reportTask = ReportTask::factory()->create();

        $historyEntry = NarrativeHistory::factory()->create([
            'narratable_type' => 'report_task',
            'narratable_id'   => $reportTask->id,
            'changed_at'      => now(),
        ]);

        $this->service->undoTaskNarrative($reportTask);

        $this->assertDatabaseMissing('narrative_history', ['id' => $historyEntry->id]);
    }

    public function test_undo_returns_null_when_no_history(): void
    {
        $reportTask = ReportTask::factory()->create();

        $result = $this->service->undoTaskNarrative($reportTask);

        $this->assertNull($result);
    }

    public function test_undo_day_narrative_restores_previous(): void
    {
        $previousNarrative = 'Previous day narrative content.';
        $reportDay = ReportDay::factory()->fromCommits()->create(['narrative' => 'Current day narrative.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => 'report_day',
            'narratable_id'      => $reportDay->id,
            'previous_narrative' => $previousNarrative,
            'changed_at'         => now(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = $this->service->undoDayNarrative($reportDay);

        $this->assertNotNull($result);
        $this->assertEquals($previousNarrative, $result->narrative);
    }

    public function test_undo_day_returns_null_when_no_history(): void
    {
        $reportDay = ReportDay::factory()->fromCommits()->create();

        $result = $this->service->undoDayNarrative($reportDay);

        $this->assertNull($result);
    }

    public function test_history_limited_to_5_entries(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Narrative v0.']);

        // Create 5 history entries directly (simulating 5 prior edits).
        for ($i = 1; $i <= 5; $i++) {
            NarrativeHistory::factory()->create([
                'narratable_type'    => 'report_task',
                'narratable_id'      => $reportTask->id,
                'previous_narrative' => "Narrative v{$i}.",
                'changed_at'         => now()->subMinutes(6 - $i),
                'source'             => NarrativeSource::ManualEdit,
            ]);
        }

        // One more edit — should trigger pruning.
        $this->service->editTaskNarrative($reportTask, 'Narrative v6.');

        $count = NarrativeHistory::query()
            ->where('narratable_type', 'report_task')
            ->where('narratable_id', $reportTask->id)
            ->count();

        $this->assertEquals(5, $count);
    }

    public function test_history_oldest_entry_deleted_when_pruned(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Narrative v0.']);

        $oldest = NarrativeHistory::factory()->create([
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Oldest narrative.',
            'changed_at'         => now()->subHour(),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        // Fill up to 5 entries total (oldest is already 1, add 4 more recent ones).
        for ($i = 1; $i <= 4; $i++) {
            NarrativeHistory::factory()->create([
                'narratable_type'    => 'report_task',
                'narratable_id'      => $reportTask->id,
                'previous_narrative' => "Narrative v{$i}.",
                'changed_at'         => now()->subMinutes(5 - $i),
                'source'             => NarrativeSource::ManualEdit,
            ]);
        }

        // One more edit — oldest should be pruned.
        $this->service->editTaskNarrative($reportTask, 'Narrative v6.');

        $this->assertDatabaseMissing('narrative_history', ['id' => $oldest->id]);
    }

    public function test_generate_for_report_calls_llm_once_per_task(): void
    {
        $report = Report::factory()->draft()->create();
        ReportTask::factory()->count(3)->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $this->assertCount(3, $this->mockLlm->narrativeRequests);
    }

    public function test_generate_for_report_day_fallback_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $reportDay->refresh();
        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $reportDay->narrative
        );
    }

    public function test_regenerate_task_llm_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;
        $reportTask = ReportTask::factory()->create(['narrative' => 'Original narrative.']);

        $result = $this->service->regenerateTask($reportTask);

        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $result->narrative
        );
    }

    public function test_regenerate_day_llm_failure_saves_placeholder(): void
    {
        $this->mockLlm->shouldFail = true;
        $reportDay = ReportDay::factory()->fromBitrix24Fallback()->create();

        $result = $this->service->regenerateDay($reportDay);

        $this->assertEquals(
            '[Не удалось сгенерировать описание. Отредактируйте вручную.]',
            $result->narrative
        );
    }

    public function test_undo_uses_latest_history_entry_by_changed_at(): void
    {
        $reportTask = ReportTask::factory()->create(['narrative' => 'Current.']);

        NarrativeHistory::factory()->create([
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Older narrative.',
            'changed_at'         => now()->subMinutes(10),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        NarrativeHistory::factory()->create([
            'narratable_type'    => 'report_task',
            'narratable_id'      => $reportTask->id,
            'previous_narrative' => 'Most recent narrative.',
            'changed_at'         => now()->subMinutes(1),
            'source'             => NarrativeSource::ManualEdit,
        ]);

        $result = $this->service->undoTaskNarrative($reportTask);

        $this->assertNotNull($result);
        $this->assertEquals('Most recent narrative.', $result->narrative);
    }

    public function test_generate_for_report_does_not_call_fallback_for_commits_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->fromCommits()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $this->assertEmpty($this->mockLlm->fallbackRequests);
    }

    public function test_generate_for_report_does_not_call_fallback_for_manual_source(): void
    {
        $report = Report::factory()->draft()->create();
        ReportDay::factory()->manuallyEdited()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

        $this->assertEmpty($this->mockLlm->fallbackRequests);
    }

    public function test_regenerate_task_passes_task_title_to_llm(): void
    {
        $task = Task::factory()->create(['title' => 'Implement login feature']);
        $reportTask = ReportTask::factory()->create([
            'task_id'   => $task->id,
            'narrative' => 'Old narrative.',
        ]);

        $this->service->regenerateTask($reportTask);

        $this->assertCount(1, $this->mockLlm->narrativeRequests);
        $this->assertEquals('Implement login feature', $this->mockLlm->narrativeRequests[0]->taskTitle);
    }

    public function test_report_status_is_generated_even_when_llm_fails(): void
    {
        $this->mockLlm->shouldFail = true;

        $report = Report::factory()->draft()->create();
        ReportTask::factory()->create(['report_id' => $report->id]);

        $this->service->generateForReport($report);

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

        $this->service->generateForReport($report);

        // Phase 1 day-task request is index 0; Phase 3 global task request follows.
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

        // Create day1 before day2 so that insertion order matches date order.
        $day1 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-11']);
        $day2 = ReportDay::factory()->fromCommits()->create(['report_id' => $report->id, 'date' => '2024-03-12']);
        ReportDayTask::factory()->create(['report_day_id' => $day1->id, 'report_task_id' => $reportTask->id]);
        ReportDayTask::factory()->create(['report_day_id' => $day2->id, 'report_task_id' => $reportTask->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-11 10:00:00']);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2024-03-12 10:00:00']);

        $this->service->generateForReport($report);

        $this->assertGreaterThanOrEqual(2, count($this->mockLlm->narrativeRequests));
        $this->assertSame([], $this->mockLlm->narrativeRequests[0]->previousNarratives);
        $this->assertSame([$this->mockLlm->narrativeText], $this->mockLlm->narrativeRequests[1]->previousNarratives);
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

        $this->service->generateForReport($report);

        $this->assertCount(2, $this->mockLlm->narrativeRequests[2]->previousNarratives);
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

        $this->service->generateForReport($report);

        // Phase 1 produces 2 day-task requests (one per task); Phase 3 adds 2 more global requests.
        $this->assertGreaterThanOrEqual(2, count($this->mockLlm->narrativeRequests));
        // Each day-task belongs to a different task — previousNarratives must be empty for both.
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

        // Set the global task narrative to the placeholder string so that when day-task generation
        // fails (LLM error), the fallback written to reportDayTask is the placeholder, and thus
        // should NOT be accumulated into previousNarratives for subsequent days.
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

        $this->service->generateForReport($report);

        // MockLlmProvider records each request before throwing. Day-task requests are indices 0 and 1
        // (Phase 1); the global reportTask request (Phase 3) adds one more entry.
        $this->assertGreaterThanOrEqual(2, count($this->mockLlm->narrativeRequests));
        // Day1 fallback is the placeholder string — it must NOT be accumulated into previousNarratives for day2.
        $this->assertSame([], $this->mockLlm->narrativeRequests[1]->previousNarratives);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLlm = new MockLlmProvider();
        $this->service = new NarrativeService($this->mockLlm);
    }
}
