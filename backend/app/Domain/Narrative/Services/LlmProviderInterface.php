<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Services;

use App\Domain\Narrative\DTOs\DayCommitsNarrativeRequest;
use App\Domain\Narrative\DTOs\DayFallbackRequest;
use App\Domain\Narrative\DTOs\DayFallbackResponse;
use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Domain\Narrative\DTOs\TaskNarrativeResponse;

interface LlmProviderInterface
{
    public function generateNarrative(TaskNarrativeRequest $request): TaskNarrativeResponse;

    public function generateDayFallback(DayFallbackRequest $request): DayFallbackResponse;

    public function generateDayFromCommits(DayCommitsNarrativeRequest $request): DayFallbackResponse;

    public function isAvailable(): bool;

    public function getProviderName(): string;

    /**
     * @return list<string> Reasons why current configuration is invalid; empty array means OK.
     */
    public function validate(): array;
}
