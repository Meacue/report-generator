<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Models\MatchResult;

final readonly class IgnoreBranch
{
    public function __invoke(int $branchId): void
    {
        $branch = Branch::findOrFail($branchId);

        MatchResult::where('branch_id', $branch->id)->forceDelete();

        MatchResult::createIgnored($branch->id);
    }
}
