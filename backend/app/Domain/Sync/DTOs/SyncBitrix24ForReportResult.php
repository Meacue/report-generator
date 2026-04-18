<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTOs;

/**
 * Result of a SyncBitrix24ForReport run.
 *
 * Carries the number of time entries upserted and the number of tasks that
 * were backfilled from Bitrix24 because they were referenced by those entries
 * but absent from the local tasks table.
 */
final readonly class SyncBitrix24ForReportResult
{
    public function __construct(
        public int $timeEntries,
        public int $tasksBackfilled,
    ) {
    }

    public function total(): int
    {
        return $this->timeEntries + $this->tasksBackfilled;
    }
}
