<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Sync\SyncService;
use App\Services\Sync\SyncServiceInterface;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SyncServiceInterface::class, SyncService::class);
    }
}
