<?php

declare(strict_types=1);

namespace App\Domain\Matching\Events;

use App\Domain\GitLab\Models\Branch;
use App\Domain\Matching\Models\MatchResult;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class BranchMatched
{
    use Dispatchable;

    public function __construct(
        public MatchResult $matchResult,
        public Branch $branch,
    ) {
    }
}
