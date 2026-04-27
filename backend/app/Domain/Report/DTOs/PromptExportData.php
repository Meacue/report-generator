<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class PromptExportData
{
    /**
     * @param  list<PromptExportPeriodTask>  $periodTasks
     * @param  list<PromptExportDay>  $days
     */
    public function __construct(
        public array $periodTasks,
        public array $days,
    ) {
    }
}
