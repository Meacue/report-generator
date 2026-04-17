<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\SyncBitrix24;
use App\Domain\Sync\Actions\SyncBitrix24Tasks;
use App\Domain\Sync\Actions\SyncBitrix24TimeEntries;
use App\Domain\Sync\DTOs\SyncBitrix24Result;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class SyncBitrix24Test extends TestCase
{
    use RefreshDatabase;

    /** @var SyncBitrix24Tasks&MockInterface */
    private SyncBitrix24Tasks $syncTasks;

    /** @var SyncBitrix24TimeEntries&MockInterface */
    private SyncBitrix24TimeEntries $syncTimeEntries;

    private SyncBitrix24 $orchestrator;

    public function test_calls_sync_tasks_once(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(5);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(0);

        $log = ($this->orchestrator)();

        $this->assertSame(5, $log->items_synced);
    }

    public function test_calls_sync_time_entries_with_7_day_period(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(0);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->withArgs(function (DateRange $period): bool {
                $expectedFrom = CarbonImmutable::now('UTC')->subDays(7)->startOfDay();

                // Allow ±5 seconds to account for test execution time
                return $period->days() === 8
                    && abs($period->from->diffInSeconds($expectedFrom)) <= 5;
            })
            ->andReturn(3);

        ($this->orchestrator)();
    }

    public function test_returns_sync_log_with_combined_item_count(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(10);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(4);

        $log = ($this->orchestrator)();

        $this->assertSame(14, $log->items_synced);
        $this->assertSame(SyncStatus::Success, $log->status);
        $this->assertSame(SyncSource::Bitrix24, $log->source);
    }

    public function test_perform_sync_returns_result_dto_with_breakdown(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(7);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(2);

        $result = $this->orchestrator->performSync();

        $this->assertInstanceOf(SyncBitrix24Result::class, $result);
        $this->assertSame(7, $result->tasks);
        $this->assertSame(2, $result->timeEntries);
        $this->assertSame(9, $result->total());
    }

    public function test_returns_failed_sync_log_when_task_sync_throws(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andThrow(new \RuntimeException('API timeout'));

        $this->syncTimeEntries
            ->shouldNotReceive('__invoke');

        $log = ($this->orchestrator)();

        $this->assertSame(SyncStatus::Failed, $log->status);
        $this->assertSame(0, $log->items_synced);
        $this->assertSame('API timeout', $log->error_message);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncTasks = Mockery::mock(SyncBitrix24Tasks::class);
        $this->syncTimeEntries = Mockery::mock(SyncBitrix24TimeEntries::class);

        $this->orchestrator = new SyncBitrix24(
            syncTasks: $this->syncTasks,
            syncTimeEntries: $this->syncTimeEntries,
        );
    }
}
