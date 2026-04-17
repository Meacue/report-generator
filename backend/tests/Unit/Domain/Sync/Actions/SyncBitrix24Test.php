<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Sync\Actions;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Actions\SyncBitrix24;
use App\Domain\Sync\Actions\SyncBitrix24Tasks;
use App\Domain\Sync\Actions\SyncBitrix24TimeEntries;
use App\Domain\Sync\DTOs\SyncBitrix24Outcome;
use App\Domain\Sync\DTOs\SyncBitrix24Result;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncLog;
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

        $outcome = ($this->orchestrator)();

        $this->assertSame(5, $outcome->log->items_synced);
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

        $outcome = ($this->orchestrator)();

        $this->assertSame(14, $outcome->log->items_synced);
        $this->assertSame(SyncStatus::Success, $outcome->log->status);
        $this->assertSame(SyncSource::Bitrix24, $outcome->log->source);
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

        $outcome = ($this->orchestrator)();

        $this->assertSame(SyncStatus::Failed, $outcome->log->status);
        $this->assertSame(0, $outcome->log->items_synced);
        $this->assertSame('API timeout', $outcome->log->error_message);
    }

    public function test_invoke_writes_sync_log_and_returns_outcome(): void
    {
        $this->syncTasks
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(3);

        $this->syncTimeEntries
            ->shouldReceive('__invoke')
            ->once()
            ->andReturn(2);

        $outcome = ($this->orchestrator)();

        $this->assertInstanceOf(SyncBitrix24Outcome::class, $outcome);
        $this->assertInstanceOf(SyncLog::class, $outcome->log);
        $this->assertInstanceOf(SyncBitrix24Result::class, $outcome->result);

        $this->assertSame(3, $outcome->result->tasks);
        $this->assertSame(2, $outcome->result->timeEntries);
        $this->assertSame(5, $outcome->log->items_synced);
        $this->assertSame(SyncStatus::Success, $outcome->log->status);

        $this->assertDatabaseHas('sync_logs', [
            'source'       => SyncSource::Bitrix24->value,
            'status'       => SyncStatus::Success->value,
            'items_synced' => 5,
        ]);
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
