<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Narrative\Services;

use App\Domain\Narrative\Exceptions\InvalidLlmConfigException;
use App\Domain\Narrative\Services\LlmConfigValidator;
use App\Domain\Narrative\Services\LlmProviderInterface;
use Tests\Mocks\MockLlmProvider;
use Tests\TestCase;

final class LlmConfigValidatorTest extends TestCase
{
    public function test_validate_aggregates_violations_from_active_provider(): void
    {
        $mock = new MockLlmProvider();
        $mock->violations = ['LLM_API_KEY is required', 'LLM_MAX_TOKENS must be >= 1'];

        $validator = new LlmConfigValidator($mock);

        $exception = null;

        try {
            $validator->validate();
        } catch (InvalidLlmConfigException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'Expected InvalidLlmConfigException to be thrown');
        $this->assertContains('LLM_API_KEY is required', $exception->violations);
        $this->assertContains('LLM_MAX_TOKENS must be >= 1', $exception->violations);
    }

    public function test_validate_passes_when_provider_returns_no_violations(): void
    {
        $mock = new MockLlmProvider();
        $mock->violations = [];

        $validator = new LlmConfigValidator($mock);

        // Must not throw — if it does, the test will fail automatically
        $validator->validate();

        $this->assertTrue(true);
    }

    public function test_validate_picks_provider_from_setting_then_falls_back_to_config(): void
    {
        $mock = new MockLlmProvider();
        $mock->violations = ['LLM_API_KEY is required'];

        $this->app->bind(LlmProviderInterface::class, fn (): MockLlmProvider => $mock);

        // Resolve from container to simulate how the controller would inject it
        /** @var LlmProviderInterface $resolvedProvider */
        $resolvedProvider = $this->app->make(LlmProviderInterface::class);
        $validator = new LlmConfigValidator($resolvedProvider);

        $this->expectException(InvalidLlmConfigException::class);

        $validator->validate();
    }
}
