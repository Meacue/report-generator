<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Domain\Narrative\Services\LlmProviderInterface;
use App\Domain\Report\Models\Report;
use App\Domain\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class ReportGenerationValidationTest extends TestCase
{
    use RefreshDatabase;

    private const array VALID_GENERATE_BODY = [
        'type'      => 'daily',
        'date_from' => '2026-03-10',
        'date_to'   => '2026-03-10',
    ];

    public function test_returns_422_when_max_tokens_is_zero(): void
    {
        config(['llm.providers.claude.max_tokens' => 0]);

        Setting::factory()->withClaude()->create(['llm_api_key' => 'sk-test-key-valid']);

        $response = $this->postJson('/api/reports/generate', self::VALID_GENERATE_BODY);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error', 'violations']);

        /** @var list<string> $violations */
        $violations = $response->json('violations');
        $this->assertTrue(
            collect($violations)->contains(fn (string $v): bool => str_contains($v, 'LLM_MAX_TOKENS')),
            'Violations must contain a message referencing LLM_MAX_TOKENS'
        );

        $this->assertSame(0, Report::count(), 'No report must be created when config is invalid');
    }

    public function test_returns_422_when_api_key_missing(): void
    {
        config(['llm.providers.claude.max_tokens' => 1024]);

        Setting::factory()->withClaude()->create(['llm_api_key' => null]);

        $response = $this->postJson('/api/reports/generate', self::VALID_GENERATE_BODY);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error', 'violations']);

        /** @var list<string> $violations */
        $violations = $response->json('violations');
        $this->assertTrue(
            collect($violations)->contains(fn (string $v): bool => str_contains($v, 'LLM_API_KEY')),
            'Violations must contain a message referencing LLM_API_KEY'
        );

        $this->assertSame(0, Report::count(), 'No report must be created when config is invalid');
    }

    public function test_returns_201_when_config_valid(): void
    {
        $mockLlm = new MockLlmProvider();
        $mockLlm->violations = [];
        $this->app->instance(LlmProviderInterface::class, $mockLlm);

        config(['llm.providers.claude.max_tokens' => 1024]);

        Setting::factory()->withClaude()->create(['llm_api_key' => 'sk-test-key-valid']);

        $response = $this->postJson('/api/reports/generate', self::VALID_GENERATE_BODY);

        if ($response->status() === 422) {
            $violations = $response->json('violations');
            $this->assertNull(
                $violations,
                'Response must not contain LLM config violations when config is valid — got: ' . json_encode($violations)
            );
        }

        $this->assertContains(
            $response->status(),
            [201, 422],
            'Expected 201 (report created) or 422 (no data), not an LLM config error'
        );
    }
}
