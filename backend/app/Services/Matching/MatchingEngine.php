<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Enums\ConfidenceLevel;
use App\Enums\ResolvedBy;
use App\Models\Branch;
use App\Models\MatchResult;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class MatchingEngine implements MatchingEngineInterface
{
    public function matchBranch(Branch $branch): MatchResult
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

    /**
     * @return Collection<int, MatchResult>
     */
    public function matchAllUnmatched(): Collection
    {
        /** @var Collection<int, Branch> $unmatchedBranches */
        $unmatchedBranches = Branch::whereDoesntHave('matchResults')->get();

        /** @var Collection<int, MatchResult> $results */
        $results = new Collection();

        foreach ($unmatchedBranches as $branch) {
            $results->push($this->matchBranch($branch));
        }

        Log::info('Matching completed', [
            'total'    => $unmatchedBranches->count(),
            'auto'     => $results->where('confidence_level', ConfidenceLevel::Auto)->count(),
            'probable' => $results->where('confidence_level', ConfidenceLevel::Probable)->count(),
            'none'     => $results->where('confidence_level', ConfidenceLevel::None)->count(),
        ]);

        return $results;
    }

    public function rematch(Branch $branch): MatchResult
    {
        $branch->matchResults()
            ->where('resolved_by', ResolvedBy::System)
            ->forceDelete();

        return $this->matchBranch($branch);
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
