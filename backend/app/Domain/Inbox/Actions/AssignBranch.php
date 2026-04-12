<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;

final readonly class AssignBranch
{
    public function __invoke(int $branchId, int $taskId): void
    {
        $branch = Branch::findOrFail($branchId);
        Task::findOrFail($taskId);

        MatchResult::where('branch_id', $branch->id)
            ->where('resolved_by', ResolvedBy::System)
            ->forceDelete();

        MatchResult::createManualMatch($branch->id, $taskId);
    }
}
