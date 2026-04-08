<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Enums\ConfidenceLevel;
use App\Enums\ResolvedBy;
use App\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final class InboxService implements InboxServiceInterface
{
    /**
     * @param  string|null  $filter  Filter: 'all', 'probable', 'none'
     * @return LengthAwarePaginator<int, Branch>
     */
    public function getUnlinkedBranches(int $perPage = 20, ?string $filter = null, string $sortDirection = 'desc'): LengthAwarePaginator
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

    public function assign(int $branchId, int $taskId): void
    {
        $branch = Branch::findOrFail($branchId);
        Task::findOrFail($taskId);

        MatchResult::where('branch_id', $branch->id)
            ->where('resolved_by', ResolvedBy::System)
            ->forceDelete();

        MatchResult::create([
            'branch_id'        => $branch->id,
            'task_id'          => $taskId,
            'confidence_level' => ConfidenceLevel::Auto,
            'resolved_by'      => ResolvedBy::User,
            'resolved_at'      => now(),
        ]);
    }

    /**
     * @param  array<int, array{branch_id: int, task_id: int}>  $assignments
     */
    public function bulkAssign(array $assignments): void
    {
        DB::transaction(function () use ($assignments): void {
            foreach ($assignments as $assignment) {
                $this->assign(
                    (int) $assignment['branch_id'],
                    (int) $assignment['task_id'],
                );
            }
        });
    }

    public function ignore(int $branchId): void
    {
        $branch = Branch::findOrFail($branchId);

        MatchResult::where('branch_id', $branch->id)->forceDelete();

        MatchResult::create([
            'branch_id'        => $branch->id,
            'task_id'          => null,
            'confidence_level' => ConfidenceLevel::None,
            'resolved_by'      => ResolvedBy::User,
            'resolved_at'      => now(),
        ]);
    }

    public function createTaskAndAssign(int $branchId, string $title): void
    {
        DB::transaction(function () use ($branchId, $title): void {
            $task = Task::create([
                'bitrix24_task_id' => 0,
                'title'            => $title,
                'status'           => TaskStatus::Completed,
                'project_name'     => 'Internal',
            ]);

            $this->assign($branchId, $task->id);
        });
    }

    /**
     * Filter branches that have no match or a non-confirmed match (none/probable),
     * excluding branches manually confirmed by user.
     *
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
