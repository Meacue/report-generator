<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Enums\ConfidenceLevel;
use App\Domain\Matching\Models\MatchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class MatchAllUnmatched
{
    public function __construct(
        private MatchBranch $matchBranch,
    ) {
    }

    /**
     * @return Collection<int, MatchResult>
     */
    public function __invoke(): Collection
    {
        /** @var Collection<int, Branch> $unmatchedBranches */
        $unmatchedBranches = Branch::whereDoesntHave('matchResults')->get();

        /** @var Collection<int, MatchResult> $results */
        $results = new Collection();

        foreach ($unmatchedBranches as $branch) {
            $results->push(($this->matchBranch)($branch));
        }

        Log::info('Matching completed', [
            'total'    => $unmatchedBranches->count(),
            'auto'     => $results->where('confidence_level', ConfidenceLevel::Auto)->count(),
            'probable' => $results->where('confidence_level', ConfidenceLevel::Probable)->count(),
            'none'     => $results->where('confidence_level', ConfidenceLevel::None)->count(),
        ]);

        return $results;
    }
}
