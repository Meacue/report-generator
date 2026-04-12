<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Actions\GenerateReport;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Events\ReportGenerated;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class GenerateReportTest extends TestCase
{
    use RefreshDatabase;

    private GenerateReport $action;

    public function test_creates_report_with_days(): void
    {
        $report = ($this->action)('weekly', new DateRange('2026-03-09', '2026-03-11'));

        $this->assertSame(ReportStatus::Generated, $report->status);
        $report->load('reportDays');
        $this->assertCount(3, $report->reportDays);
    }

    public function test_links_tasks_to_report(): void
    {
        $task = Task::factory()->create();
        $branch = Branch::factory()->create();
        MatchResult::factory()->create(['branch_id' => $branch->id, 'task_id' => $task->id]);
        Commit::factory()->create(['branch_id' => $branch->id, 'committed_at' => '2026-03-10 14:00:00']);

        $report = ($this->action)('daily', new DateRange('2026-03-10', '2026-03-10'));

        $report->load('reportTasks');
        $this->assertCount(1, $report->reportTasks);
        $this->assertSame($task->id, $report->reportTasks->first()?->task_id);
    }

    public function test_day_without_commits_gets_fallback_source(): void
    {
        $report = ($this->action)('daily', new DateRange('2026-03-10', '2026-03-10'));

        $report->load('reportDays');
        $this->assertSame(ReportDaySource::Bitrix24Fallback, $report->reportDays->first()?->source);
    }

    public function test_placeholder_narrative_from_commits(): void
    {
        $branch = Branch::factory()->create();
        Commit::factory()->create([
            'branch_id'    => $branch->id,
            'committed_at' => '2026-03-10 10:00:00',
            'message'      => 'feat: add login endpoint',
        ]);

        $report = ($this->action)('daily', new DateRange('2026-03-10', '2026-03-10'));

        $report->load('reportDays');
        $reportDay = $report->reportDays->first();
        $this->assertNotNull($reportDay?->narrative);
        $this->assertStringContainsString('Выполнены коммиты:', $reportDay->narrative);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ReportGenerated::class]);
        $this->action = new GenerateReport();
    }
}
