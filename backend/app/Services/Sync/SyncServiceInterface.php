<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Sync\Models\SyncLog;

interface SyncServiceInterface
{
    /**
     * Run full synchronization: GitLab -> Bitrix24 -> Match.
     */
    public function syncAll(): void;

    /**
     * Sync only GitLab data (branches + commits).
     */
    public function syncGitLab(): SyncLog;

    /**
     * Sync only Bitrix24 tasks.
     */
    public function syncBitrix24(): SyncLog;

    /**
     * Resync for a specific date range.
     */
    public function resync(DateRange $dateRange): void;
}
