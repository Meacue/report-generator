<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Domain\Narrative\DTOs\DayCommitsNarrativeRequest;
use App\Domain\Narrative\DTOs\DayFallbackRequest;
use App\Domain\Narrative\DTOs\DayFallbackResponse;
use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Domain\Narrative\DTOs\TaskNarrativeResponse;
use App\Domain\Narrative\Services\LlmProviderInterface;

final class MockLlmProvider implements LlmProviderInterface
{
    /** @var array<int, TaskNarrativeRequest> */
    public array $narrativeRequests = [];

    /** @var array<int, DayFallbackRequest> */
    public array $fallbackRequests = [];

    /** @var array<int, DayCommitsNarrativeRequest> */
    public array $dayCommitsRequests = [];

    public bool $shouldFail = false;

    public string $narrativeText = 'Mock narrative text for testing.';

    public string $fallbackText = 'Mock fallback text for empty day.';

    public string $dayCommitsText = 'Mock day commits narrative for testing.';

    public function generateNarrative(TaskNarrativeRequest $request): TaskNarrativeResponse
    {
        $this->narrativeRequests[] = $request;

        if ($this->shouldFail) {
            throw new \RuntimeException('LLM provider error');
        }

        return new TaskNarrativeResponse(
            narrative: $this->narrativeText,
            provider: 'mock',
            tokensUsed: 100,
        );
    }

    public function generateDayFallback(DayFallbackRequest $request): DayFallbackResponse
    {
        $this->fallbackRequests[] = $request;

        if ($this->shouldFail) {
            throw new \RuntimeException('LLM provider error');
        }

        return new DayFallbackResponse(
            narrative: $this->fallbackText,
            provider: 'mock',
            tokensUsed: 50,
        );
    }

    public function generateDayFromCommits(DayCommitsNarrativeRequest $request): DayFallbackResponse
    {
        $this->dayCommitsRequests[] = $request;

        if ($this->shouldFail) {
            throw new \RuntimeException('LLM provider error');
        }

        return new DayFallbackResponse(
            narrative: $this->dayCommitsText,
            provider: 'mock',
            tokensUsed: 75,
        );
    }

    public function isAvailable(): bool
    {
        return ! $this->shouldFail;
    }

    public function getProviderName(): string
    {
        return 'mock';
    }
}
