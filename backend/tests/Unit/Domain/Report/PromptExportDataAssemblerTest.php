<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\DTOs\PromptExportData;
use App\Domain\Report\DTOs\PromptExportDay;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
use App\Domain\Report\Services\PromptExportDataAssembler;
use App\Domain\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PromptExportDataAssemblerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // #1 — period tasks total_seconds summed across days
    // -------------------------------------------------------------------------

    public function test_returns_period_tasks_with_total_seconds_summed_across_days(): void
    {
        // GIVEN: Setting + Task + Report with TimeEntries on 3 separate days.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70001]);
        $report = Report::factory()->create([
            'type'      => 'custom',
            'date_from' => '2026-04-13',
            'date_to'   => '2026-04-15',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70001,
            'bitrix24_user_id' => '1',
            'seconds'          => 1000,
            'tracked_at'       => '2026-04-13 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70001,
            'bitrix24_user_id' => '1',
            'seconds'          => 2000,
            'tracked_at'       => '2026-04-14 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70001,
            'bitrix24_user_id' => '1',
            'seconds'          => 3000,
            'tracked_at'       => '2026-04-15 09:00:00',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: single period task with totalSeconds = 1000 + 2000 + 3000 = 6000.
        $this->assertCount(1, $data->periodTasks);
        $this->assertSame(6000, $data->periodTasks[0]->totalSeconds);
    }

    // -------------------------------------------------------------------------
    // #2 — orphan tasks (timeline only, no ReportTask) appear in periodTasks
    // -------------------------------------------------------------------------

    public function test_includes_orphan_tasks_in_period_tasks(): void
    {
        // GIVEN: Task exists + TimeEntry, but NO ReportTask linking them.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        Task::factory()->create([
            'bitrix24_task_id' => 70002,
            'title'            => 'Orphan Task',
        ]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);
        // No ReportTask created intentionally.
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70002,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-14 10:00:00',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: orphan appears in periodTasks with status === null.
        $periodTask = $this->findPeriodTask($data, 70002);
        $this->assertNotNull($periodTask);
        $this->assertNull($periodTask->status);
        $this->assertSame('Orphan Task', $periodTask->title);
    }

    // -------------------------------------------------------------------------
    // #4 — days count matches period length
    // -------------------------------------------------------------------------

    public function test_days_count_matches_period_length(): void
    {
        // GIVEN: report spanning 3 days.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $report = Report::factory()->create([
            'type'      => 'custom',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-16',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: exactly 3 days returned, in ascending date order.
        $this->assertCount(3, $data->days);
        $this->assertSame('2026-04-14', $data->days[0]->date);
        $this->assertSame('2026-04-15', $data->days[1]->date);
        $this->assertSame('2026-04-16', $data->days[2]->date);
    }

    // -------------------------------------------------------------------------
    // #5 — day with no activity is flagged isEmpty = true
    // -------------------------------------------------------------------------

    public function test_day_is_marked_empty_when_no_tasks_or_time(): void
    {
        // GIVEN: report on 2 days; only the first has a TimeEntry.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70005]);
        $report = Report::factory()->create([
            'type'      => 'custom',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-15',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70005,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-14 09:00:00',
        ]);
        // No TimeEntry or ReportDay for 2026-04-15.

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: second day (2026-04-15) is empty.
        $secondDay = $this->findDay($data, '2026-04-15');
        $this->assertNotNull($secondDay);
        $this->assertTrue($secondDay->isEmpty);
        $this->assertSame([], $secondDay->tasks);
    }

    // -------------------------------------------------------------------------
    // #6 — day includes tasks from both reportDayTasks and timeline
    // -------------------------------------------------------------------------

    public function test_day_includes_tasks_from_both_report_day_tasks_and_timeline(): void
    {
        // GIVEN: task A via ReportDayTask, task B via TimeEntry only.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $taskA = Task::factory()->create(['bitrix24_task_id' => 70006]);
        $taskB = Task::factory()->create(['bitrix24_task_id' => 70007]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);

        // Task A linked through ReportTask + ReportDay + ReportDayTask.
        $reportTaskA = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $taskA->id]);
        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-14',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTaskA->id,
        ]);

        // Task B: only a TimeEntry (no ReportDayTask, no ReportTask).
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70007,
            'bitrix24_user_id' => '1',
            'seconds'          => 900,
            'tracked_at'       => '2026-04-14 14:00:00',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: both task A and task B appear in the day's tasks list.
        $day = $this->findDay($data, '2026-04-14');
        $this->assertNotNull($day);
        $dayTaskIds = array_map(fn ($t) => $t->bitrix24TaskId, $day->tasks);
        $this->assertContains(70006, $dayTaskIds);
        $this->assertContains(70007, $dayTaskIds);
    }

    // -------------------------------------------------------------------------
    // #7 — day task includes commits from matched branch
    // -------------------------------------------------------------------------

    public function test_day_task_includes_commits_for_matched_branch(): void
    {
        // GIVEN: ReportTask + Task + Branch + MatchResult + Commit on the report date.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70008]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'message'      => 'feat: implement feature X',
            'committed_at' => '2026-04-14 10:00:00',
        ]);

        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-14',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: the day task contains the commit message.
        $day = $this->findDay($data, '2026-04-14');
        $this->assertNotNull($day);
        $this->assertCount(1, $day->tasks);
        $this->assertContains('feat: implement feature X', $day->tasks[0]->commits);
    }

    // -------------------------------------------------------------------------
    // #9 — day task narrative taken from ReportDayTask
    // -------------------------------------------------------------------------

    public function test_day_task_narrative_taken_from_report_day_task(): void
    {
        // GIVEN: ReportDayTask.narrative set to a specific string.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70009]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);
        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => 'report-task-narrative',
        ]);
        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-14',
            'narrative' => 'report-day-narrative',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
            'narrative'      => 'per-day-нарратив',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: only the per-day-task narrative is stored in the DTO.
        $day = $this->findDay($data, '2026-04-14');
        $this->assertNotNull($day);
        $this->assertCount(1, $day->tasks);
        $this->assertSame('per-day-нарратив', $day->tasks[0]->narrative);
    }

    // -------------------------------------------------------------------------
    // #11 — day source is set correctly when ReportDay exists
    // -------------------------------------------------------------------------

    public function test_day_source_is_commits_or_bitrix24_fallback_when_present(): void
    {
        // GIVEN: two days; day 1 has source=Commits, day 2 has source=Bitrix24Fallback.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70011]);
        $report = Report::factory()->create([
            'type'      => 'custom',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-15',
        ]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $reportDay1 = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-14',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay1->id,
            'report_task_id' => $reportTask->id,
        ]);

        $reportDay2 = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-15',
            'source'    => ReportDaySource::Bitrix24Fallback,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay2->id,
            'report_task_id' => $reportTask->id,
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: sources match what was stored in ReportDay.
        $day1 = $this->findDay($data, '2026-04-14');
        $day2 = $this->findDay($data, '2026-04-15');
        $this->assertNotNull($day1);
        $this->assertNotNull($day2);
        $this->assertSame(ReportDaySource::Commits, $day1->source);
        $this->assertSame(ReportDaySource::Bitrix24Fallback, $day2->source);
    }

    // -------------------------------------------------------------------------
    // Bonus — day source is null when no ReportDay record exists
    // -------------------------------------------------------------------------

    public function test_day_source_is_null_when_no_report_day_record(): void
    {
        // GIVEN: TimeEntry on date but NO ReportDay record for that date.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 70012]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 70012,
            'bitrix24_user_id' => '1',
            'seconds'          => 1200,
            'tracked_at'       => '2026-04-14 11:00:00',
        ]);
        // Intentionally no ReportDay::factory() call.

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: the day DTO has source === null.
        $day = $this->findDay($data, '2026-04-14');
        $this->assertNotNull($day);
        $this->assertNull($day->source);
    }

    // -------------------------------------------------------------------------
    // Bonus — period tasks falls back when task not in tasks table
    // -------------------------------------------------------------------------

    public function test_period_tasks_falls_back_when_task_not_in_tasks_table(): void
    {
        // GIVEN: TimeEntry referencing a bitrix24_task_id that has no Task row.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-14',
            'date_to'   => '2026-04-14',
        ]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 79999,
            'bitrix24_user_id' => '1',
            'seconds'          => 600,
            'tracked_at'       => '2026-04-14 08:00:00',
        ]);

        // WHEN:
        $data = $this->makeAssembler()->assemble($report);

        // THEN: period task exists with fallback title containing the id and marker.
        $periodTask = $this->findPeriodTask($data, 79999);
        $this->assertNotNull($periodTask);
        $this->assertStringContainsString('#79999', $periodTask->title);
        $this->assertStringContainsString('нет в Bitrix24', $periodTask->title);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAssembler(): PromptExportDataAssembler
    {
        return new PromptExportDataAssembler(
            new GetTaskTimeTimeline(),
            new NarrativeSupport(),
        );
    }

    private function findPeriodTask(PromptExportData $data, int $bitrix24TaskId): ?\App\Domain\Report\DTOs\PromptExportPeriodTask
    {
        foreach ($data->periodTasks as $task) {
            if ($task->bitrix24TaskId === $bitrix24TaskId) {
                return $task;
            }
        }

        return null;
    }

    private function findDay(PromptExportData $data, string $date): ?PromptExportDay
    {
        foreach ($data->days as $day) {
            if ($day->date === $date) {
                return $day;
            }
        }

        return null;
    }
}
