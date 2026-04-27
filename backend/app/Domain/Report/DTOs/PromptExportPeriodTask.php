<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class PromptExportPeriodTask
{
    public function __construct(
        public int $bitrix24TaskId,
        public string $title,
        public ?string $projectName,
        public ?string $status,
        public int $totalSeconds,
    ) {
    }
}
