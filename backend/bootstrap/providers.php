<?php

use App\Providers\AppServiceProvider;
use App\Providers\Bitrix24ServiceProvider;
use App\Providers\GitLabServiceProvider;
use App\Providers\LlmServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\ReportServiceProvider;

return [
    AppServiceProvider::class,
    Bitrix24ServiceProvider::class,
    EventServiceProvider::class,
    GitLabServiceProvider::class,
    LlmServiceProvider::class,
    ReportServiceProvider::class,
];
