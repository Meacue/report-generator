<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\LLM;

use App\Domain\Narrative\DTOs\TaskNarrativeRequest;
use App\Infrastructure\LLM\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiProviderTest extends TestCase
{
    private const string FAKE_API_KEY = 'sk-test-openai-key-1234567890abcdef';

    private const string FAKE_MODEL = 'gpt-4o';

    private const string OPENAI_API_PATTERN = 'https://api.openai.com/*';

    public function test_validate_returns_violation_when_max_tokens_is_zero(): void
    {
        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, 0, 'system prompt');

        $violations = $provider->validate();

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('LLM_MAX_TOKENS must be >= 1', implode(', ', $violations));
    }

    public function test_validate_returns_violation_when_max_tokens_is_negative(): void
    {
        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, -5, 'system prompt');

        $violations = $provider->validate();

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('LLM_MAX_TOKENS must be >= 1', implode(', ', $violations));
    }

    public function test_validate_returns_violation_when_api_key_is_empty(): void
    {
        $provider = new OpenAiProvider('', self::FAKE_MODEL, 1024, 'system prompt');

        $violations = $provider->validate();

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('LLM_API_KEY is required', implode(', ', $violations));
    }

    public function test_validate_returns_empty_when_config_is_valid(): void
    {
        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, 1024, 'system prompt');

        $violations = $provider->validate();

        $this->assertSame([], $violations);
    }

    public function test_generate_narrative_throws_when_max_tokens_below_one(): void
    {
        Http::fake();

        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, 0, 'system prompt');
        $request = new TaskNarrativeRequest(
            taskTitle: 'Test task',
            projectName: 'Test project',
            commits: ['feat: add feature'],
        );

        $threw = false;

        try {
            $provider->generateNarrative($request);
        } catch (\InvalidArgumentException) {
            $threw = true;
        } catch (\Throwable) {
        }

        Http::assertNothingSent();
        $this->assertTrue($threw, 'Expected InvalidArgumentException before any HTTP request is made');
    }

    public function test_generate_narrative_throws_when_api_returns_empty_content(): void
    {
        Http::fake([
            self::OPENAI_API_PATTERN => Http::response([
                'choices' => [
                    ['message' => ['content' => '']],
                ],
                'usage' => ['total_tokens' => 10],
            ], 200),
        ]);

        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, 1024, 'system prompt');
        $request = new TaskNarrativeRequest(
            taskTitle: 'Test task',
            projectName: 'Test project',
            commits: ['feat: add feature'],
        );

        $this->expectException(\RuntimeException::class);

        $provider->generateNarrative($request);
    }

    public function test_generate_narrative_succeeds_on_valid_response(): void
    {
        $expectedNarrative = 'Generated narrative text from OpenAI.';

        Http::fake([
            self::OPENAI_API_PATTERN => Http::response([
                'choices' => [
                    ['message' => ['content' => $expectedNarrative]],
                ],
                'usage' => ['total_tokens' => 150],
            ], 200),
        ]);

        $provider = new OpenAiProvider(self::FAKE_API_KEY, self::FAKE_MODEL, 1024, 'system prompt');
        $request = new TaskNarrativeRequest(
            taskTitle: 'Test task',
            projectName: 'Test project',
            commits: ['feat: add feature'],
        );

        $response = $provider->generateNarrative($request);

        $this->assertSame($expectedNarrative, $response->narrative);

        Http::assertSent(function (\Illuminate\Http\Client\Request $sentRequest): bool {
            $body = json_decode($sentRequest->body(), true);

            return is_array($body) && isset($body['max_tokens']) && $body['max_tokens'] === 1024;
        });
    }
}
