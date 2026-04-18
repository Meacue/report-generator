<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Report\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\Models\MatchResult;
use App\Domain\Report\Actions\GenerateReport;
use App\Domain\Report\Enums\ReportDaySource;
use App\Domain\Report\Enums\ReportStatus;
use App\Domain\Report\Events\ReportGenerated;
use App\Domain\Report\Queries\GetCommitsForDate;
use App\Domain\Report\Queries\GetTaskIdsFromCommits;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\SyncBitrix24ForReport;
use App\Domain\Sync\DTOs\SyncBitrix24ForReportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class GenerateReportTest extends TestCase
{
    use RefreshDatabase;

    private SyncBitrix24ForReport&MockInterface $syncMock;

    private GenerateReport $action;

    public function test_creates_report_with_days(): void
    {
        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(new SyncBitrix24ForReportResult(timeEntries: 0, tasksBackfilled: 0));

        $report = ($this->action)('weekly', new DateRange('2026-03-09', '2026-03-11'));

        $this->assertSame(ReportStatus::Generated, $report->status);
        $report->load('reportDays');
        $this->assertCount(3, $report->reportDays);
    }

    public function test_links_tasks_to_report(): void
    {
        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(new SyncBitrix24ForReportResult(timeEntries: 0, tasksBackfilled: 0));

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
        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(new SyncBitrix24ForReportResult(timeEntries: 0, tasksBackfilled: 0));

        $report = ($this->action)('daily', new DateRange('2026-03-10', '2026-03-10'));

        $report->load('reportDays');
        $this->assertSame(ReportDaySource::Bitrix24Fallback, $report->reportDays->first()?->source);
    }

    public function test_placeholder_narrative_from_commits(): void
    {
        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(new SyncBitrix24ForReportResult(timeEntries: 0, tasksBackfilled: 0));

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

    public function test_invokes_sync_for_report_before_generation(): void
    {
        $period = new DateRange('2026-03-10', '2026-03-10');

        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::on(
                fn (DateRange $arg): bool => $arg->from->toDateString() === '2026-03-10'
                    && $arg->to->toDateString() === '2026-03-10'
            ))
            ->andReturn(new SyncBitrix24ForReportResult(timeEntries: 0, tasksBackfilled: 0));

        ($this->action)('daily', $period);
    }

    public function test_continues_generation_when_sync_fails_with_runtime_error(): void
    {
        Log::spy();

        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andThrow(new RuntimeException('Connection timeout'));

        // Generation should still produce a report (with fallback day)
        $report = ($this->action)('daily', new DateRange('2026-03-10', '2026-03-10'));

        $this->assertSame(ReportStatus::Generated, $report->status);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Report-time sync failed', Mockery::on(
                fn (array $ctx): bool => str_contains($ctx['error'], 'Connection timeout'),
            ));
    }

    public function test_rethrows_when_period_exceeds_30_days(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->syncMock
            ->shouldReceive('__invoke')
            ->once()
            ->andThrow(new InvalidArgumentException('Report period cannot exceed 30 days'));

        ($this->action)('weekly', new DateRange('2026-01-01', '2026-02-01'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ReportGenerated::class]);

        $this->syncMock = Mockery::mock(SyncBitrix24ForReport::class);

        $this->action = new GenerateReport(
            new GetCommitsForDate(),
            new GetTaskIdsFromCommits(),
            $this->syncMock,
        );
    }
}
