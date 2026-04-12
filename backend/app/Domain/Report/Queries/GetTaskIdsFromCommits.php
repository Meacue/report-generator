<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Matching\Models\MatchResult;
use Illuminate\Support\Collection;

final readonly class GetTaskIdsFromCommits
{
    /**
     * @param  Collection<int, \App\Domain\GitLab\Models\Commit>  $commits
     * @return list<int>
     */
    public function __invoke(Collection $commits): array
    {
        /** @var list<int> $branchIds */
        $branchIds = $commits->pluck('branch_id')->unique()->filter()->values()->all();

        if ($branchIds === []) {
            return [];
        }

        /** @var list<int> */
        return MatchResult::whereIn('branch_id', $branchIds)
            ->whereNotNull('task_id')
            ->distinct()
            ->pluck('task_id')
            ->all();
    }
}
