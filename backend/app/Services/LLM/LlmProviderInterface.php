<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\DTOs\DayCommitsNarrativeRequest;
use App\DTOs\DayFallbackRequest;
use App\DTOs\DayFallbackResponse;
use App\DTOs\TaskNarrativeRequest;
use App\DTOs\TaskNarrativeResponse;

interface LlmProviderInterface
{
    public function generateNarrative(TaskNarrativeRequest $request): TaskNarrativeResponse;

    public function generateDayFallback(DayFallbackRequest $request): DayFallbackResponse;

    public function generateDayFromCommits(DayCommitsNarrativeRequest $request): DayFallbackResponse;

    public function isAvailable(): bool;

    public function getProviderName(): string;
}
