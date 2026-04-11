<?php

declare(strict_types=1);

namespace App\Domain\Narrative\DTOs;

final readonly class DayCommitsNarrativeRequest
{
    /**
     * @param  array<int, string>  $commits
     */
    public function __construct(
        public string $date,
        public array $commits,
        public ?string $systemPrompt = null,
    ) {
    }
}
