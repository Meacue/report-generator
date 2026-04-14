<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportDay
{
    /**
     * @param  list<ReportExportTask>  $tasks
     */
    public function __construct(
        public string $date,
        public array $tasks,
        public ?string $narrative = null,
    ) {
    }
}
