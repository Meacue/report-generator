<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTOs;

use App\Domain\Sync\Models\SyncLog;

/**
 * Bundled result of a full SyncBitrix24::__invoke() call.
 *
 * Combines the persisted SyncLog record with the detailed breakdown
 * (task and time-entry counts) from SyncBitrix24Result, so callers can
 * both check the log status and surface the human-readable breakdown
 * without calling performSync() directly.
 */
final readonly class SyncBitrix24Outcome
{
    public function __construct(
        public SyncLog $log,
        public SyncBitrix24Result $result,
    ) {
    }
}
