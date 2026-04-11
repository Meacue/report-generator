<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use Illuminate\Support\Facades\DB;

final readonly class BulkAssignBranches
{
    public function __construct(
        private AssignBranch $assignBranch,
    ) {
    }

    /**
     * @param  array<int, array{branch_id: int, task_id: int}>  $assignments
     */
    public function __invoke(array $assignments): void
    {
        DB::transaction(function () use ($assignments): void {
            foreach ($assignments as $assignment) {
                ($this->assignBranch)(
                    (int) $assignment['branch_id'],
                    (int) $assignment['task_id'],
                );
            }
        });
    }
}
