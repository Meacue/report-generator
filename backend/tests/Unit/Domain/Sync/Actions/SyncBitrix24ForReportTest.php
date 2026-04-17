<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\EnsureTasksForPeriod;
use App\Domain\Sync\Actions\SyncBitrix24ForReport;
use App\Domain\Sync\Actions\SyncBitrix24TimeEntries;
use App\Domain\Sync\DTOs\SyncBitrix24ForReportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class SyncBitrix24ForReportTest extends TestCase
{
    use RefreshDatabase;

    private SyncBitrix24TimeEntries&MockInterface $syncTimeEntries;

    private EnsureTasksForPeriod&MockInterface $ensureTasksForPeriod;

    private SyncBitrix24ForReport $action;

    public function test_syncs_time_entries_then_backfills_tasks(): void
    {
        // GIVEN: sync returns 5 time entries; DB has 3 TimeEntry rows in the period
        $period = new DateRange('2026-03-01', '2026-03-07');

        TimeEntry::factory()->create(['bitrix24_task_id' => 10, 'tracked_at' => '2026-03-02 10:00:00']);
        TimeEntry::factory()->create(['bitrix24_task_id' => 20, 'tracked_at' => '2026-03-03 11:00:00']);
        TimeEntry::factory()->create(['bitrix24_task_id' => 30, 'tracked_at' => '2026-03-04 12:00:00']);
        // Entry outside the period — must not be included
        TimeEntry::factory()->create(['bitrix24_task_id' => 99, 'tracked_at' => '2026-02-10 10:00:00']);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::type(DateRange::class))
            ->andReturn(5);

        $this->ensureTasksForPeriod
            ->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::on(function (array $ids): bool {
                sort($ids);

                return $ids === [10, 20, 30];
            }))
            ->andReturn(2);

        ($this->action)($period);
    }

    public function test_returns_breakdown_in_result_dto(): void
    {
        $period = new DateRange('2026-03-01', '2026-03-07');

        TimeEntry::factory()->create(['bitrix24_task_id' => 50, 'tracked_at' => '2026-03-02 10:00:00']);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(7);

        $this->ensureTasksForPeriod
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(3);

        $result = ($this->action)($period);

        $this->assertInstanceOf(SyncBitrix24ForReportResult::class, $result);
        $this->assertSame(7, $result->timeEntries);
        $this->assertSame(3, $result->tasksBackfilled);
        $this->assertSame(10, $result->total());
    }

    public function test_only_distinct_task_ids_are_passed_to_ensure(): void
    {
        // GIVEN: multiple TimeEntry rows share the same bitrix24_task_id
        $period = new DateRange('2026-03-01', '2026-03-07');

        // Two entries for task 10, two for task 20
        TimeEntry::factory()->create(['bitrix24_task_id' => 10, 'tracked_at' => '2026-03-02 09:00:00']);
        TimeEntry::factory()->create(['bitrix24_task_id' => 10, 'tracked_at' => '2026-03-03 09:00:00']);
        TimeEntry::factory()->create(['bitrix24_task_id' => 20, 'tracked_at' => '2026-03-04 09:00:00']);
        TimeEntry::factory()->create(['bitrix24_task_id' => 20, 'tracked_at' => '2026-03-05 09:00:00']);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(4);

        $this->ensureTasksForPeriod
            ->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::on(function (array $ids): bool {
                sort($ids);

                return count($ids) === 2 && $ids === [10, 20];
            }))
            ->andReturn(0);

        ($this->action)($period);
    }

    public function test_throws_invalid_argument_when_period_exceeds_30_days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Report period cannot exceed 30 days');

        // 32 days
        $period = new DateRange('2026-01-01', '2026-02-01');

        $this->syncTimeEntries->shouldNotReceive('__invoke');
        $this->ensureTasksForPeriod->shouldNotReceive('__invoke');

        ($this->action)($period);
    }

    public function test_lock_protects_concurrent_runs(): void
    {
        // Acquire the lock externally to simulate another process holding it
        $lock = Cache::lock('bitrix24-report-sync', 120);
        $lock->acquire();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Another report sync is already in progress');

            $period = new DateRange('2026-03-01', '2026-03-07');

            $this->syncTimeEntries->shouldNotReceive('__invoke');
            $this->ensureTasksForPeriod->shouldNotReceive('__invoke');

            ($this->action)($period);
        } finally {
            $lock->release();
        }
    }

    public function test_returns_zero_backfill_when_no_entries_in_period(): void
    {
        $period = new DateRange('2026-03-01', '2026-03-07');

        // No TimeEntry rows at all → EnsureTasksForPeriod receives []
        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(0);

        $this->ensureTasksForPeriod
            ->shouldReceive('__invoke')
            ->once()
            ->with([])
            ->andReturn(0);

        $result = ($this->action)($period);

        $this->assertSame(0, $result->timeEntries);
        $this->assertSame(0, $result->tasksBackfilled);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncTimeEntries = Mockery::mock(SyncBitrix24TimeEntries::class);
        $this->ensureTasksForPeriod = Mockery::mock(EnsureTasksForPeriod::class);

        $this->action = new SyncBitrix24ForReport(
            syncTimeEntries: $this->syncTimeEntries,
            ensureTasksForPeriod: $this->ensureTasksForPeriod,
        );
    }
}
