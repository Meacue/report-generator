<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTOs;

/**
 * Aggregated result of a single Bitrix24 synchronisation run.
 *
 * Returned by SyncBitrix24::performSync() so the orchestrator can log
 * a breakdown without exposing internal action classes to SyncCommand.
 */
final readonly class SyncBitrix24Result
{
    public function __construct(
        public int $tasks,
        public int $timeEntries,
    ) {
    }

    public function total(): int
    {
        return $this->tasks + $this->timeEntries;
    }
}
