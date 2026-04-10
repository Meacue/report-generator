<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Models;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_generated_changes_status_to_generated(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Draft]);

        $report->markAsGenerated();
        $report->refresh();

        $this->assertSame(ReportStatus::Generated, $report->status);
    }

    public function test_mark_as_exported_changes_status_to_exported(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Generated]);

        $report->markAsExported();
        $report->refresh();

        $this->assertSame(ReportStatus::Exported, $report->status);
    }

    public function test_is_editable_returns_false_for_exported_report(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Exported]);

        $this->assertFalse($report->isEditable());
    }

    public function test_is_editable_returns_true_for_draft_report(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Draft]);

        $this->assertTrue($report->isEditable());
    }

    public function test_is_editable_returns_true_for_generated_report(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Generated]);

        $this->assertTrue($report->isEditable());
    }

    public function test_can_be_regenerated_returns_true_for_draft(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Draft]);

        $this->assertTrue($report->canBeRegenerated());
    }

    public function test_can_be_regenerated_returns_true_for_generated(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Generated]);

        $this->assertTrue($report->canBeRegenerated());
    }

    public function test_can_be_regenerated_returns_false_for_exported(): void
    {
        $report = Report::factory()->create(['status' => ReportStatus::Exported]);

        $this->assertFalse($report->canBeRegenerated());
    }

    public function test_get_date_range_returns_date_range_instance(): void
    {
        $report = Report::factory()->create([
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $this->assertInstanceOf(DateRange::class, $report->getDateRange());
    }

    public function test_get_date_range_has_correct_from_date(): void
    {
        $report = Report::factory()->create([
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $this->assertSame('2024-01-01', $report->getDateRange()->from->toDateString());
    }

    public function test_get_date_range_has_correct_to_date(): void
    {
        $report = Report::factory()->create([
            'date_from' => '2024-01-01',
            'date_to'   => '2024-01-31',
        ]);

        $this->assertSame('2024-01-31', $report->getDateRange()->to->toDateString());
    }
}
