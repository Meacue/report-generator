<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use App\Domain\Report\Enums\ReportDaySource;

final readonly class PromptExportDay
{
    /**
     * @param  list<PromptExportDayTask>  $tasks
     */
    public function __construct(
        public string $date,
        public string $dayOfWeek,
        public ?ReportDaySource $source,
        public bool $isEmpty,
        public array $tasks,
    ) {
    }
}
