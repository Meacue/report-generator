<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Bitrix24\Enums\TaskStatus;
use App\Domain\Bitrix24\Models\Task;
use Illuminate\Support\Facades\DB;

final readonly class CreateTaskAndAssign
{
    public function __construct(
        private AssignBranch $assignBranch,
    ) {
    }

    public function __invoke(int $branchId, string $title): void
    {
        DB::transaction(function () use ($branchId, $title): void {
            $task = Task::create([
                'bitrix24_task_id' => 0,
                'title'            => $title,
                'status'           => TaskStatus::Completed,
                'project_name'     => 'Internal',
            ]);

            ($this->assignBranch)($branchId, $task->id);
        });
    }
}
