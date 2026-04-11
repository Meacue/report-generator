<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportDayTask;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            ReportTask::MORPH_ALIAS    => ReportTask::class,
            ReportDay::MORPH_ALIAS     => ReportDay::class,
            ReportDayTask::MORPH_ALIAS => ReportDayTask::class,
        ]);
    }
}
