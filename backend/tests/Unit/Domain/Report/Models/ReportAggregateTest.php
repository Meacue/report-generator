<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Models;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_day_creates_report_day(): void
    {
        $report = Report::factory()->create();

        $day = $report->addDay('2024-01-15', ReportDaySource::Commits);

        $this->assertSame($report->id, $day->report_id);
        $this->assertSame('2024-01-15', $day->date->toDateString());
        $this->assertSame(ReportDaySource::Commits, $day->source);
        $this->assertFalse($day->is_edited);
        $this->assertNull($day->narrative);
    }

    public function test_add_day_with_narrative(): void
    {
        $report = Report::factory()->create();

        $day = $report->addDay('2024-01-15', ReportDaySource::Commits, 'Some narrative');

        $this->assertSame('Some narrative', $day->narrative);
    }

    public function test_add_task_creates_report_task(): void
    {
        $report = Report::factory()->create();
        $task = Task::factory()->create();

        $reportTask = $report->addTask($task->id, 'My Project');

        $this->assertSame($report->id, $reportTask->report_id);
        $this->assertSame($task->id, $reportTask->task_id);
        $this->assertSame('My Project', $reportTask->project_name);
        $this->assertFalse($reportTask->is_edited);
        $this->assertNull($reportTask->narrative);
    }

    public function test_find_day_returns_existing(): void
    {
        $report = Report::factory()->create();
        $report->addDay('2024-01-15', ReportDaySource::Commits);

        $found = $report->findDay('2024-01-15');

        $this->assertNotNull($found);
        $this->assertSame('2024-01-15', $found->date->toDateString());
    }

    public function test_find_day_returns_null_for_missing(): void
    {
        $report = Report::factory()->create();

        $this->assertNull($report->findDay('2024-01-15'));
    }

    public function test_find_task_returns_existing(): void
    {
        $report = Report::factory()->create();
        $task = Task::factory()->create();
        $report->addTask($task->id, 'Project');

        $found = $report->findTask($task->id);

        $this->assertNotNull($found);
        $this->assertSame($task->id, $found->task_id);
    }

    public function test_find_task_returns_null_for_missing(): void
    {
        $report = Report::factory()->create();

        $this->assertNull($report->findTask(999));
    }

    public function test_guard_exportable_throws_for_draft(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Draft]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot export a draft report');

        $report->guardExportable();
    }

    public function test_guard_exportable_allows_generated(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Generated]);

        $report->guardExportable();

        $this->assertTrue(true); // no exception thrown
    }

    public function test_guard_exportable_allows_exported(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Exported]);

        $report->guardExportable();

        $this->assertTrue(true);
    }
}
