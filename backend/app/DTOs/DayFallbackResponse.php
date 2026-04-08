<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DayFallbackResponse
{
    public function __construct(
        public string $narrative,
        public string $provider,
        public int $tokensUsed,
    ) {
    }
}
