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
            'report_task'     => ReportTask::class,
            'report_day'      => ReportDay::class,
            'report_day_task' => ReportDayTask::class,
        ]);
    }
}
