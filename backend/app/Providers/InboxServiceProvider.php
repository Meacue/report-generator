<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Inbox\InboxService;
use App\Services\Inbox\InboxServiceInterface;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InboxServiceInterface::class, InboxService::class);
    }
}
