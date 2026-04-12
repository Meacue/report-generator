<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\GitLab\Models\Commit;
use Illuminate\Support\Collection;

final readonly class GetCommitsForDate
{
    /**
     * @return Collection<int, Commit>
     */
    public function __invoke(string $date): Collection
    {
        return Commit::whereDate('committed_at', $date)->get();
    }
}
