<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;

final readonly class RematchBranch
{
    public function __construct(
        private MatchBranch $matchBranch,
    ) {
    }

    public function __invoke(Branch $branch): MatchResult
    {
        $branch->matchResults()
            ->where('resolved_by', ResolvedBy::System)
            ->forceDelete();

        return ($this->matchBranch)($branch);
    }
}
