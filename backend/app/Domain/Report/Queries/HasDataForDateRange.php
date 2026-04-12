<?php

declare(strict_types=1);

namespace App\Domain\Report\Queries;

use App\Domain\Bitrix24\Models\Task;
use App\Domain\GitLab\Models\Commit;
use App\Domain\Shared\ValueObjects\DateRange;

final readonly class HasDataForDateRange
{
    public function __invoke(DateRange $dateRange): bool
    {
        $commitsExist = Commit::query()
            ->whereBetween('committed_at', [$dateRange->from->startOfDay(), $dateRange->to->endOfDay()])
            ->exists();

        if ($commitsExist) {
            return true;
        }

        return Task::query()
            ->whereBetween('status_changed_at', [$dateRange->from->startOfDay(), $dateRange->to->endOfDay()])
            ->exists();
    }
}
