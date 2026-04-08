<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DayFallbackRequest
{
    /**
     * @param  array<int, string>  $taskTitles
     */
    public function __construct(
        public string $date,
        public array $taskTitles,
        public ?string $systemPrompt = null,
    ) {
    }
}
