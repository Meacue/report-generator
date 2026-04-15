<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportMonthlyData
{
    /**
     * @param  list<ReportExportMonthlyDay>  $days
     * @param  list<ReportExportUnclassifiedCommit>  $unclassifiedCommits
     */
    public function __construct(
        public string $developerName,
        public string $developerPosition,
        public string $dateFrom,
        public string $dateTo,
        public array $days,
        public array $unclassifiedCommits = [],
    ) {
    }
}
