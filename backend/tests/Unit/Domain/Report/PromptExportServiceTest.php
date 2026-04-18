<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Report\Models\Report;
use App\Domain\Report\Models\ReportTask;
use App\Domain\Report\Queries\GetTaskTimeTimeline;
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
        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

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
        $svc = $this->makeService();
        $prompt = $svc->buildPromptFile($report);

        // THEN: the time line is present under the task.
        $this->assertStringContainsString('Время по задаче (суммарно за период): 2:30:15', $prompt);
    }

    public function test_omits_time_line_when_seconds_zero_or_null(): void
    {
        // GIVEN: a task with NO time entries.
        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 99001]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-10',
            'date_to'   => '2026-04-10',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        // WHEN:
        $svc = $this->makeService();
        $prompt = $svc->buildPromptFile($report);

        // THEN: no "Время по задаче" line.
        $this->assertStringNotContainsString('Время по задаче', $prompt);
    }

    public function test_appends_day_chronology_section_when_timeline_has_data(): void
    {
        // GIVEN: a report with time entries on a specific day.
        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 57706]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-12',
            'date_to'   => '2026-04-12',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        TimeEntry::factory()->create([
            'bitrix24_task_id' => 57706,
            'bitrix24_user_id' => '1',
            'seconds'          => 9015,
            'tracked_at'       => '2026-04-12 09:00:00',
        ]);

        // WHEN:
        $svc = $this->makeService();
        $prompt = $svc->buildPromptFile($report);

        // THEN: chronology block with d.m.Y date format and h:mm:ss time.
        $this->assertStringContainsString('--- ХРОНОЛОГИЯ ДНЯ ---', $prompt);
        $this->assertStringContainsString('12.04.2026:', $prompt);
        $this->assertStringContainsString('#57706 — 2:30:15', $prompt);
    }

    public function test_omits_chronology_when_timeline_empty(): void
    {
        // GIVEN: no time entries at all.
        \App\Domain\Settings\Models\Setting::factory()->create(['bitrix24_user_id' => '1']);

        $task = Task::factory()->create(['bitrix24_task_id' => 99002]);
        $report = Report::factory()->create([
            'type'      => 'daily',
            'date_from' => '2026-04-12',
            'date_to'   => '2026-04-12',
        ]);
        ReportTask::factory()->create(['report_id' => $report->id, 'task_id' => $task->id]);

        // WHEN:
        $svc = $this->makeService();
        $prompt = $svc->buildPromptFile($report);

        // THEN: no chronology block.
        $this->assertStringNotContainsString('ХРОНОЛОГИЯ ДНЯ', $prompt);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeService(): PromptExportService
    {
        return new PromptExportService(new GetTaskTimeTimeline());
    }
}
