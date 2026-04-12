<?php

declare(strict_types=1);

namespace App\Domain\Matching\Listeners;

use App\Domain\Matching\Actions\MatchAllUnmatched;
use App\Domain\Sync\Events\SyncCompleted;

final readonly class MatchBranchesOnSyncCompleted
{
    public function __construct(
        private MatchAllUnmatched $matchAllUnmatched,
    ) {
    }

    public function handle(SyncCompleted $event): void
    {
        ($this->matchAllUnmatched)();
    }
}
