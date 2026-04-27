<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class PromptExportDayTask
{
    /**
     * @param  list<string>  $commits
     */
    public function __construct(
        public int $bitrix24TaskId,
        public string $title,
        public ?string $projectName,
        public int $seconds,
        public array $commits,
        public ?string $narrative,
    ) {
    }
}
