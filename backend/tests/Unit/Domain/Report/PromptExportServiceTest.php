<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
use App\Domain\Report\Services\PromptExportDataAssembler;
use App\Domain\Settings\Models\Setting;
use App\Infrastructure\Report\PromptExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PromptExportServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // formatHms unit tests (no DB needed)
    // -------------------------------------------------------------------------

    public function test_formats_zero_seconds(): void
    {
        $svc = $this->makeService();
        $this->assertSame('0:00:00', $svc->formatHms(0));
    }

    public function test_formats_seconds_only(): void
    {
        $svc = $this->makeService();
        $this->assertSame('0:05:30', $svc->formatHms(330));
    }

    public function test_formats_standard_hms(): void
    {
        $svc = $this->makeService();
        $this->assertSame('2:30:15', $svc->formatHms(9015));
    }

    public function test_formats_large_hms_correctly(): void
    {
        // 288 * 3600 = 1036800 seconds.
        $svc = $this->makeService();
        $this->assertSame('288:00:00', $svc->formatHms(1_036_800));
    }

    public function test_no_leading_zero_on_hours(): void
    {
        $svc = $this->makeService();
        // 1 hour exactly.
        $this->assertSame('1:00:00', $svc->formatHms(3600));
    }

    // -------------------------------------------------------------------------
    // Integration tests with DB
    // -------------------------------------------------------------------------

    public function test_includes_time_line_under_task_when_seconds_present(): void
    {
        // GIVEN: a report task tied to a Bitrix24 task, with time entries.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 57706]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-10',
            'date_to'   => '2026-04-10',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => '1',
            'seconds'          => 9015,
            'tracked_at'       => '2026-04-10 09:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: the period task line contains the task id and hms total.
        $this->assertStringContainsString('### #57706', $prompt);
        $this->assertStringContainsString('2:30:15', $prompt);
    }

    public function test_omits_time_line_when_seconds_zero_or_null(): void
    {
        // GIVEN: a task with NO time entries.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 99001]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-10',
            'date_to'   => '2026-04-10',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: no hms pattern appears next to the task id in the period section.
        $this->assertDoesNotMatchRegularExpression('/### #99001.*— \d+:\d{2}:\d{2}/', $prompt);
    }

    public function test_renders_per_day_section_with_commits_under_task(): void
    {
        // GIVEN: report on 2026-04-14 (Tuesday) with task, commits and a ReportDay.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 58284]);
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
            'message'      => 'feat: add list endpoints',
            'committed_at' => '2026-04-14 12:00:00',
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

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 58284,
            'bitrix24_user_id' => '1',
            'seconds'          => 5217,
            'tracked_at'       => '2026-04-14 09:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: day header with correct date and day name.
        $this->assertStringContainsString('### 2026-04-14 (Вторник)', $prompt);

        // THEN: tasks-of-day label.
        $this->assertStringContainsString('Задачи дня:', $prompt);

        // THEN: task line with id and hms.
        $this->assertStringContainsString('#58284:', $prompt);

        // THEN: commit message appears.
        $this->assertStringContainsString('feat: add list endpoints', $prompt);

        // THEN: commit section header present.
        $this->assertStringContainsString('Коммиты:', $prompt);
    }

    public function test_day_with_time_but_no_commits_shows_task_with_zero_commits(): void
    {
        // GIVEN: report with TimeEntry but no Branch/Commit and no MatchResult.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 58500]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-15',
            'date_to'   => '2026-04-15',
        ]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-15',
            'source'    => ReportDaySource::Bitrix24Fallback,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 58500,
            'bitrix24_user_id' => '1',
            'seconds'          => 3600,
            'tracked_at'       => '2026-04-15 10:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: task appears in the day section.
        $this->assertStringContainsString('#58500:', $prompt);

        // THEN: no-commits placeholder is shown.
        $this->assertStringContainsString('Коммиты: (нет за этот день)', $prompt);
    }

    public function test_day_without_any_activity_shows_no_activity_marker(): void
    {
        // GIVEN: 3-day report; only the first and last days have a TimeEntry.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 58600]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-13',
            'date_to'   => '2026-04-15',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 58600,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-13 09:00:00',
        ]);
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 58600,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-15 09:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: middle day (2026-04-14) is marked with «нет активности».
        $this->assertStringContainsString('2026-04-14', $prompt);
        $this->assertStringContainsString('нет активности', $prompt);
    }

    public function test_resolves_title_from_tasks_table_for_timeline_only_task(): void
    {
        // GIVEN: Task exists in the tasks table but has NO ReportTask.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        Task::factory()->create([
            'bitrix24_task_id' => 99999,
            'title'            => 'Orphan Task Title',
        ]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-10',
            'date_to'   => '2026-04-10',
        ]);

        // Only a TimeEntry, no ReportTask.
        TimeEntry::factory()->create([
            'bitrix24_task_id' => 99999,
            'bitrix24_user_id' => '1',
            'seconds'          => 3600,
            'tracked_at'       => '2026-04-10 11:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: task appears in ЗАДАЧИ ПЕРИОДА with its proper title.
        $this->assertStringContainsString('--- ЗАДАЧИ ПЕРИОДА ---', $prompt);
        $this->assertStringContainsString('#99999', $prompt);
        $this->assertStringContainsString('Orphan Task Title', $prompt);
    }

    public function test_falls_back_when_bitrix24_task_id_not_in_tasks_table(): void
    {
        // GIVEN: TimeEntry referencing a bitrix24_task_id that does not exist in tasks.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-10',
            'date_to'   => '2026-04-10',
        ]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 88888,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-10 14:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: fallback marker is present.
        $this->assertStringContainsString('#88888', $prompt);
        $this->assertStringContainsString('нет в Bitrix24', $prompt);
    }

    public function test_uses_report_day_task_narrative_only(): void
    {
        // GIVEN: narratives at three levels; only the per-day-task one must appear in the day block.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 58700]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-16',
            'date_to'   => '2026-04-16',
        ]);
        $reportTask = ReportTask::factory()->create([
            'report_id' => $report->id,
            'task_id'   => $task->id,
            'narrative' => 'глобал-нарратив',
        ]);
        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-16',
            'narrative' => 'день-нарратив',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
            'narrative'      => 'per-day-task-нарратив',
        ]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 58700,
            'bitrix24_user_id' => '1',
            'seconds'          => 1800,
            'tracked_at'       => '2026-04-16 09:00:00',
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: only the per-day-task narrative appears inside the day block.
        $this->assertStringContainsString('Нарратив (день): per-day-task-нарратив', $prompt);

        // THEN: other narrative levels are NOT present in the output.
        $this->assertStringNotContainsString('день-нарратив', $prompt);
        $this->assertStringNotContainsString('глобал-нарратив', $prompt);
    }

    public function test_does_not_include_mr_metadata(): void
    {
        // GIVEN: a branch with full MR metadata.
        Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 58800]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-17',
            'date_to'   => '2026-04-17',
        ]);
        $reportTask = ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        $branch = Branch::factory()->create([
            'mr_title'         => 'My Merge Request',
            'mr_description'   => 'Detailed MR description',
            'mr_additions'     => 120,
            'mr_deletions'     => 30,
            'mr_changed_files' => ['src/Foo.php', 'src/Bar.php'],
        ]);
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);

        $reportDay = ReportDay::factory()->create([
            'report_id' => $report->id,
            'date'      => '2026-04-17',
            'source'    => ReportDaySource::Commits,
        ]);
        ReportDayTask::factory()->create([
            'report_day_id'  => $reportDay->id,
            'report_task_id' => $reportTask->id,
        ]);

        // WHEN:
        $prompt = $this->makeService()->buildPromptFile($report);

        // THEN: MR metadata strings are absent.
        $this->assertStringNotContainsString('Название MR', $prompt);
        $this->assertStringNotContainsString('Описание MR', $prompt);
        $this->assertStringNotContainsString('Статистика', $prompt);
        $this->assertStringNotContainsString('Изменённые файлы', $prompt);

        // THEN: legacy section headers are absent.
        $this->assertStringNotContainsString('--- ХРОНОЛОГИЯ ДНЯ ---', $prompt);
        $this->assertStringNotContainsString('--- ДНИ БЕЗ КОММИТОВ ---', $prompt);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeService(): PromptExportService
    {
        return new PromptExportService(
            new PromptExportDataAssembler(
                new GetTaskTimeTimeline(),
                new NarrativeSupport(),
            ),
        );
    }
}
