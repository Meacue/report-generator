<?php

use App\Providers\AppServiceProvider;
use App\Providers\Bitrix24ServiceProvider;
use App\Providers\GitLabServiceProvider;
use App\Providers\InboxServiceProvider;
use App\Providers\LlmServiceProvider;
use App\Providers\NarrativeServiceProvider;
use App\Providers\ReportServiceProvider;

return [
    AppServiceProvider::class,
    Bitrix24ServiceProvider::class,
    GitLabServiceProvider::class,
    InboxServiceProvider::class,
    LlmServiceProvider::class,
    NarrativeServiceProvider::class,
    ReportServiceProvider::class,
];
