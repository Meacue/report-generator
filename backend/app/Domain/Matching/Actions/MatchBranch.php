<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;

final readonly class MatchBranch
{
    public function __invoke(Branch $branch): MatchResult
    {
        $parsedTaskNumber = $branch->parsed_task_number;

        if ($parsedTaskNumber !== null) {
            /** @var Task|null $task */
            $task = Task::where('bitrix24_task_id', (int) $parsedTaskNumber)->first();

            if ($task instanceof Task) {
                return $this->createOrUpdateMatch($branch, $task, ConfidenceLevel::Auto);
            }

            return $this->createOrUpdateMatch($branch, null, ConfidenceLevel::Probable);
        }

        return $this->createOrUpdateMatch($branch, null, ConfidenceLevel::None);
    }

    private function createOrUpdateMatch(
        Branch $branch,
        ?Task $task,
        ConfidenceLevel $confidence,
    ): MatchResult {
        /** @var MatchResult */
        return MatchResult::updateOrCreate(
            [
                'branch_id' => $branch->id,
                'task_id'   => $task?->id,
            ],
            [
                'confidence_level' => $confidence,
                'resolved_by'      => ResolvedBy::System,
                'resolved_at'      => now(),
            ],
        );
    }
}
