<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LLM\LlmManager;
use App\Services\Narrative\NarrativeService;
use App\Services\Narrative\NarrativeServiceInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class NarrativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NarrativeServiceInterface::class, function (Application $app): NarrativeService {
            /** @var LlmManager $llmManager */
            $llmManager = $app->make(LlmManager::class);

            return new NarrativeService($llmManager->provider());
        });
    }
}
