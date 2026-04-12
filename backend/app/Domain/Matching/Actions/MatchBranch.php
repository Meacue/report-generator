<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Events\BranchMatched;
use App\Domain\Matching\Enums\ResolvedBy;
use App\Domain\Matching\Models\MatchResult;

final readonly class MatchBranch
{
    public function __invoke(Branch $branch): MatchResult
    {
        [$task, $confidence] = $this->resolve($branch);

        $matchResult = $this->createOrUpdateMatch($branch, $task, $confidence);

        BranchMatched::dispatch($matchResult, $branch);

        return $matchResult;
    }

    /**
     * @return array{0: Task|null, 1: ConfidenceLevel}
     */
    private function resolve(Branch $branch): array
    {
        $parsedTaskNumber = $branch->parsed_task_number;

        if ($parsedTaskNumber === null) {
            return [null, ConfidenceLevel::None];
        }

        /** @var Task|null $task */
        $task = Task::where('bitrix24_task_id', (int) $parsedTaskNumber)->first();

        if ($task instanceof Task) {
            return [$task, ConfidenceLevel::Auto];
        }

        return [null, ConfidenceLevel::Probable];
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
