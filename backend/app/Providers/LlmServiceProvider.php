<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Models\Setting;
use App\Services\LLM\ClaudeProvider;
use App\Services\LLM\LlmManager;
use App\Services\LLM\LlmProviderInterface;
use App\Services\LLM\OpenAiProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmManager::class, function (): LlmManager {
            /** @var array{default: string, providers: array{claude: array{api_key: string|null, model: string, max_tokens: int}, openai: array{api_key: string|null, model: string, max_tokens: int}}, default_system_prompt: string} $config */
            $config = config('llm');

            $dbSettings = $this->loadDbSettings();

            $defaultProvider = $dbSettings['provider'] ?? $config['default'];
            $apiKey = $dbSettings['api_key'] ?? null;
            $systemPrompt = $dbSettings['system_prompt'] ?? $config['default_system_prompt'];

            $claudeApiKey = $defaultProvider === 'claude' && $apiKey !== null
                ? $apiKey
                : (string) ($config['providers']['claude']['api_key'] ?? '');

            $openaiApiKey = $defaultProvider === 'openai' && $apiKey !== null
                ? $apiKey
                : (string) ($config['providers']['openai']['api_key'] ?? '');

            $providers = [
                'claude' => new ClaudeProvider(
                    apiKey: $claudeApiKey,
                    model: $config['providers']['claude']['model'],
                    maxTokens: $config['providers']['claude']['max_tokens'],
                    defaultSystemPrompt: $systemPrompt,
                ),
                'openai' => new OpenAiProvider(
                    apiKey: $openaiApiKey,
                    model: $config['providers']['openai']['model'],
                    maxTokens: $config['providers']['openai']['max_tokens'],
                    defaultSystemPrompt: $systemPrompt,
                ),
            ];

            return new LlmManager(
                providers: $providers,
                defaultProvider: $defaultProvider,
            );
        });

        $this->app->bind(LlmProviderInterface::class, function (): LlmProviderInterface {
            /** @var LlmManager $manager */
            $manager = $this->app->make(LlmManager::class);

            return $manager->provider();
        });
    }

    /**
     * Load LLM settings from the database with graceful fallback.
     *
     * @return array{provider: string|null, api_key: string|null, system_prompt: string|null}
     */
    private function loadDbSettings(): array
    {
        try {
            /** @var Setting|null $setting */
            $setting = Setting::query()->first();

            if ($setting === null) {
                return ['provider' => null, 'api_key' => null, 'system_prompt' => null];
            }

            return [
                'provider'      => $setting->llm_provider?->value,
                'api_key'       => $setting->llm_api_key,
                'system_prompt' => $setting->llm_system_prompt,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to load LLM settings from database, using config fallback', [
                'error' => $e->getMessage(),
            ]);

            return ['provider' => null, 'api_key' => null, 'system_prompt' => null];
        }
    }
}
