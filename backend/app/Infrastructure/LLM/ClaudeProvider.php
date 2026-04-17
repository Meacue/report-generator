<?php

declare(strict_types=1);

namespace App\Infrastructure\LLM;

use App\Domain\Narrative\DTOs\DayCommitsNarrativeRequest;
use App\Domain\Narrative\DTOs\DayFallbackRequest;
use App\Domain\Narrative\DTOs\DayFallbackResponse;
use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Domain\Narrative\DTOs\TaskNarrativeResponse;
use App\Domain\Narrative\Services\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClaudeProvider implements LlmProviderInterface
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';

    private const int MAX_RETRIES = 3;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly string $defaultSystemPrompt,
    ) {
    }

    public function generateNarrative(TaskNarrativeRequest $request): TaskNarrativeResponse
    {
        $systemPrompt = $request->systemPrompt ?? $this->defaultSystemPrompt;
        $commitsText = implode("\n- ", $request->commits);

        $userPrompt = "Опиши выполненную работу по задаче «{$request->taskTitle}» "
            . "в проекте «{$request->projectName}».\n";

        if ($request->mrTitle !== null) {
            $userPrompt .= "Название MR: {$request->mrTitle}\n";
        }

        if ($request->mrDescription !== null) {
            $userPrompt .= "Описание MR: {$request->mrDescription}\n";
        }

        $userPrompt .= "Коммиты:\n- {$commitsText}\n";

        if ($request->totalAdditions !== null || $request->totalDeletions !== null) {
            $additions = $request->totalAdditions ?? 0;
            $deletions = $request->totalDeletions ?? 0;
            $userPrompt .= "Статистика изменений: +{$additions} / -{$deletions} строк\n";
        }

        if ($request->changedFiles !== []) {
            $filesText = implode(', ', $request->changedFiles);
            $userPrompt .= "Изменённые файлы: {$filesText}\n";
        }

        if ($request->previousNarratives !== []) {
            $dayNumber = count($request->previousNarratives) + 1;
            $previousText = implode("\n---\n", $request->previousNarratives);
            $userPrompt .= "\nЭто день {$dayNumber} работы над данной задачей. "
                . "Ранее по этой задаче уже были написаны описания:\n{$previousText}\n"
                . 'Не повторяй вводные фразы и формулировки из предыдущих описаний. '
                . "Сосредоточься на конкретных изменениях за текущий день.\n";
        }

        $userPrompt .= "\nНапиши 2-3 предложения на русском языке в деловом стиле.";

        $response = $this->sendRequest($systemPrompt, $userPrompt);

        return new TaskNarrativeResponse(
            narrative: $response['narrative'],
            provider: $this->getProviderName(),
            tokensUsed: $response['tokens_used'],
        );
    }

    public function generateDayFallback(DayFallbackRequest $request): DayFallbackResponse
    {
        $systemPrompt = $request->systemPrompt ?? $this->defaultSystemPrompt;
        $tasksText = implode(', ', $request->taskTitles);

        $userPrompt = "Опиши рабочий день {$request->date}. "
            . "Задачи: {$tasksText}. "
            . '2-3 предложения на русском.';

        $response = $this->sendRequest($systemPrompt, $userPrompt);

        return new DayFallbackResponse(
            narrative: $response['narrative'],
            provider: $this->getProviderName(),
            tokensUsed: $response['tokens_used'],
        );
    }

    public function generateDayFromCommits(DayCommitsNarrativeRequest $request): DayFallbackResponse
    {
        $systemPrompt = $request->systemPrompt ?? $this->defaultSystemPrompt;
        $commitsText = implode("\n- ", $request->commits);

        $userPrompt = "Опиши рабочий день {$request->date}. "
            . "Коммиты:\n- {$commitsText}\n\n"
            . 'Напиши 2-3 предложения на русском языке в деловом стиле, описывая что было сделано. Не перечисляй коммиты.';

        $response = $this->sendRequest($systemPrompt, $userPrompt);

        return new DayFallbackResponse(
            narrative: $response['narrative'],
            provider: $this->getProviderName(),
            tokensUsed: $response['tokens_used'],
        );
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function getProviderName(): string
    {
        return 'claude';
    }

    /**
     * @return array{narrative: string, tokens_used: int}
     */
    private function sendRequest(string $systemPrompt, string $userPrompt): array
    {
        try {
            return retry(
                self::MAX_RETRIES,
                function (int $attempt) use ($systemPrompt, $userPrompt): array {
                    try {
                        return $this->executeApiCall($systemPrompt, $userPrompt);
                    } catch (\Throwable $e) {
                        Log::warning("Claude API attempt {$attempt} failed", [
                            'error'   => $e->getMessage(),
                            'attempt' => $attempt,
                        ]);
                        throw $e;
                    }
                },
                fn (int $attempt): int => 1000 * (2 ** ($attempt - 1)),
            );
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Claude API failed after ' . self::MAX_RETRIES . ' attempts: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * @return array{narrative: string, tokens_used: int}
     */
    private function executeApiCall(string $systemPrompt, string $userPrompt): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(30)->post(self::API_URL, [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $systemPrompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Claude API returned status {$response->status()}: {$response->body()}"
            );
        }

        /** @var array{content: array<int, array{text: string}>, usage: array{input_tokens: int, output_tokens: int}} $data */
        $data = $response->json();

        $narrative = $data['content'][0]['text'] ?? '';
        $tokensUsed = $data['usage']['input_tokens'] + $data['usage']['output_tokens'];

        return [
            'narrative'   => $narrative,
            'tokens_used' => $tokensUsed,
        ];
    }
}
