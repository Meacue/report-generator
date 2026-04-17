<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Bitrix24\Models\TimeEntry;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\DTOs\SyncBitrix24ForReportResult;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

/**
 * Flow 2: pull time entries for a report period, then backfill any Bitrix24
 * tasks referenced by those entries that are not yet in the local tasks table.
 *
 * Protected by a 120-second Cache::lock so that concurrent report generations
 * cannot race each other. If the lock is already held, a RuntimeException is
 * thrown — callers should treat this as a degradable infrastructure failure.
 *
 * A 30-day period limit is enforced as a domain guard (the FormRequest also
 * validates this at the HTTP layer to return a proper 422 before the action
 * is ever invoked).
 */
class SyncBitrix24ForReport
{
    private const MAX_DAYS = 30;

    public function __construct(
        private readonly SyncBitrix24TimeEntries $syncTimeEntries,
        private readonly EnsureTasksForPeriod $ensureTasksForPeriod,
    ) {
    }

    public function __invoke(DateRange $period): SyncBitrix24ForReportResult
    {
        if ($period->exceeds(self::MAX_DAYS)) {
            throw new InvalidArgumentException(
                'Report period cannot exceed ' . self::MAX_DAYS . ' days'
            );
        }

        /** @var SyncBitrix24ForReportResult|false $result */
        $result = Cache::lock('bitrix24-report-sync', 120)->get(
            fn (): SyncBitrix24ForReportResult => $this->perform($period)
        );

        if ($result === false) {
            throw new RuntimeException('Another report sync is already in progress');
        }

        return $result;
    }

    private function perform(DateRange $period): SyncBitrix24ForReportResult
    {
        // 1. Sync time entries for the period
        $timeEntriesCount = ($this->syncTimeEntries)($period);

        // 2. Collect distinct Bitrix24 task IDs from entries in this period
        /** @var list<int> $taskIds */
        $taskIds = TimeEntry::query()
            ->whereBetween('tracked_at', [$period->from, $period->to])
            ->distinct()
            ->pluck('bitrix24_task_id')
            ->map(static function (mixed $id): int {
                /** @var int|string $id */
                return (int) $id;
            })
            ->values()
            ->all();

        // 3. Backfill tasks missing from the local table
        $tasksBackfilled = ($this->ensureTasksForPeriod)($taskIds);

        return new SyncBitrix24ForReportResult(
            timeEntries: $timeEntriesCount,
            tasksBackfilled: $tasksBackfilled,
        );
    }
}
