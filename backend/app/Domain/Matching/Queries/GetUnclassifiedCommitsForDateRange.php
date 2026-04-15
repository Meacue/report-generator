<?php

declare(strict_types=1);

namespace App\Domain\Matching\Queries;

use App\Domain\GitLab\Models\Commit;
use App\Domain\Matching\DTOs\UnclassifiedCommit;
use App\Domain\Settings\Models\ProjectMapping;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Database\Eloquent\Builder;

final readonly class GetUnclassifiedCommitsForDateRange
{
    /**
     * Find commits in the given date range on branches that have no
     * MatchResult with a non-null task_id — i.e. branches either
     * never processed or explicitly ignored.
     *
     * @return list<UnclassifiedCommit>
     */
    public function __invoke(DateRange $dateRange): array
    {
        $commits = Commit::query()
            ->with('branch')
            ->whereBetween('committed_at', [$dateRange->from, $dateRange->to])
            ->whereHas('branch', function (Builder $q): void {
                $q->whereDoesntHave('matchResults', function (Builder $inner): void {
                    $inner->whereNotNull('task_id');
                });
            })
            ->orderBy('committed_at')
            ->get();

        $repoIds = $commits
            ->map(static fn (Commit $commit): ?int => $commit->branch?->gitlab_repo_id)
            ->filter()
            ->unique()
            ->values();

        /** @var array<int, string> $repoNameMap */
        $repoNameMap = ProjectMapping::query()
            ->whereIn('gitlab_repo_id', $repoIds)
            ->pluck('gitlab_repo_name', 'gitlab_repo_id')
            ->all();

        $result = [];

        foreach ($commits as $commit) {
            $branch = $commit->branch;

            if ($branch === null) {
                continue;
            }

            $result[] = new UnclassifiedCommit(
                repoName: $repoNameMap[$branch->gitlab_repo_id] ?? '',
                message: $commit->message,
                branchName: $branch->branch_name,
            );
        }

        return $result;
    }
}
