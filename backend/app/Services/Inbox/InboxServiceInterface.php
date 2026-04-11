<?php

declare(strict_types=1);

namespace App\Services\Inbox;

use App\Domain\GitLab\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InboxServiceInterface
{
    /**
     * Get paginated list of unlinked branches with their commits.
     *
     * @param  string|null  $filter  Filter: 'all', 'probable', 'none'
     * @return LengthAwarePaginator<int, Branch>
     */
    public function getUnlinkedBranches(int $perPage = 20, ?string $filter = null, string $sortDirection = 'desc'): LengthAwarePaginator;

    /**
     * Manually assign a branch to a task.
     */
    public function assign(int $branchId, int $taskId): void;

    /**
     * Bulk assign multiple branches to tasks.
     *
     * @param  array<int, array{branch_id: int, task_id: int}>  $assignments
     */
    public function bulkAssign(array $assignments): void;

    /**
     * Mark a branch as ignored (internal work, no task).
     */
    public function ignore(int $branchId): void;

    /**
     * Create an internal task and assign it to the branch.
     */
    public function createTaskAndAssign(int $branchId, string $title): void;
}
