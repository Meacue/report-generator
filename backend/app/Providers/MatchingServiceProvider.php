<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Matching\MatchingEngine;
use App\Services\Matching\MatchingEngineInterface;
use Illuminate\Support\ServiceProvider;

final class MatchingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            MatchingEngineInterface::class,
            MatchingEngine::class,
        );
    }
}
