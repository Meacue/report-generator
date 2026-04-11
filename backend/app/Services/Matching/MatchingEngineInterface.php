<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Models\MatchResult;
use Illuminate\Support\Collection;

interface MatchingEngineInterface
{
    /**
     * Match a single branch to tasks.
     */
    public function matchBranch(Branch $branch): MatchResult;

    /**
     * Match all unmatched branches.
     *
     * @return Collection<int, MatchResult>
     */
    public function matchAllUnmatched(): Collection;

    /**
     * Re-match a specific branch (e.g. after user adds new tasks).
     * Deletes existing system matches and re-runs matching.
     */
    public function rematch(Branch $branch): MatchResult;
}
