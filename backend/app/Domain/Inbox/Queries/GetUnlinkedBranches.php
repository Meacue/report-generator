<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Queries;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class GetUnlinkedBranches
{
    /**
     * @param  string|null  $filter  Filter: 'all', 'probable', 'none'
     * @return LengthAwarePaginator<int, Branch>
     */
    public function __invoke(int $perPage = 20, ?string $filter = null, string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $sortDirection = in_array($sortDirection, ['asc', 'desc'], true) ? $sortDirection : 'desc';

        $query = Branch::query()
            ->with([
                'commits' => function (Relation $q): void {
                    $q->orderByDesc('committed_at')->limit(5);
                },
                'matchResults',
            ]);

        if ($filter === 'probable') {
            $query->whereHas('matchResults', function (Builder $q): void {
                $q->where('confidence_level', ConfidenceLevel::Probable);
            });
        } elseif ($filter === 'none') {
            $query->where(function (Builder $q): void {
                $q->whereHas('matchResults', function (Builder $sub): void {
                    $sub->where('confidence_level', ConfidenceLevel::None);
                })->orWhereDoesntHave('matchResults');
            });
        } else {
            $this->filterAllUnlinkedBranches($query);
        }

        return $query
            ->orderByRaw('CASE WHEN parsed_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw("parsed_date {$sortDirection}")
            ->paginate($perPage);
    }

    /**
     * @param  Builder<Branch>  $query
     */
    private function filterAllUnlinkedBranches(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereDoesntHave('matchResults')
                ->orWhereHas('matchResults', function (Builder $sub): void {
                    $sub->whereIn('confidence_level', [
                        ConfidenceLevel::None,
                        ConfidenceLevel::Probable,
                    ]);
                });
        })
            ->whereDoesntHave('matchResults', function (Builder $sub): void {
                $sub->where('confidence_level', ConfidenceLevel::Auto)
                    ->where('resolved_by', ResolvedBy::User);
            });
    }
}
