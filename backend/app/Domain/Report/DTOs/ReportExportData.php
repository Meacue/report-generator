<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportData
{
    /**
     * @param  list<ReportExportDay>  $days
     * @param  list<ReportExportUnclassifiedCommit>|null  $unclassifiedCommits
     */
    public function __construct(
        public string $type,
        public string $developerName,
        public string $developerPosition,
        public string $dateFrom,
        public string $dateTo,
        public array $days,
        public ?array $unclassifiedCommits = null,
    ) {
    }
}
