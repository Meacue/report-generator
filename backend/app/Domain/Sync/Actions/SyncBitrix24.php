<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\DTOs\SyncBitrix24Result;
use App\Domain\Sync\Enums\SyncSource;
use App\Domain\Sync\Enums\SyncStatus;
use App\Domain\Sync\Models\SyncLog;
use Carbon\CarbonImmutable;

/**
 * Orchestrator for Bitrix24 synchronisation (Flow 1).
 *
 * Delegates task syncing to SyncBitrix24Tasks and time-entry syncing to
 * SyncBitrix24TimeEntries, then writes a single SyncLog entry with the
 * combined item count.
 *
 * The public __invoke() signature is unchanged — it still returns a SyncLog —
 * so all existing callers (SyncCommand, SyncJob, tests) continue to work.
 */
final readonly class SyncBitrix24
{
    /**
     * Time-entry safety-net window: sync the last N days to cover Inbox gaps.
     */
    private const TIME_ENTRIES_DAYS = 7;

    public function __construct(
        private SyncBitrix24Tasks $syncTasks,
        private SyncBitrix24TimeEntries $syncTimeEntries,
    ) {
    }

    public function __invoke(): SyncLog
    {
        $startedAt = CarbonImmutable::now();

        try {
            $result = $this->performSync();

            return $this->createSyncLog(
                status: SyncStatus::Success,
                itemsSynced: $result->total(),
                startedAt: $startedAt,
            );
        } catch (\Throwable $e) {
            return $this->createSyncLog(
                status: SyncStatus::Failed,
                itemsSynced: 0,
                startedAt: $startedAt,
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Run both sub-actions and return the aggregated result.
     */
    public function performSync(): SyncBitrix24Result
    {
        $tasks = ($this->syncTasks)();
        $timeEntries = ($this->syncTimeEntries)(DateRange::lastDays(self::TIME_ENTRIES_DAYS));

        return new SyncBitrix24Result(
            tasks: $tasks,
            timeEntries: $timeEntries,
        );
    }

    private function createSyncLog(
        SyncStatus $status,
        int $itemsSynced,
        CarbonImmutable $startedAt,
        ?string $errorMessage = null,
    ): SyncLog {
        /** @var SyncLog */
        return SyncLog::query()->create([
            'source'        => SyncSource::Bitrix24,
            'status'        => $status,
            'items_synced'  => $itemsSynced,
            'error_message' => $errorMessage,
            'started_at'    => $startedAt,
            'completed_at'  => CarbonImmutable::now(),
        ]);
    }
}
