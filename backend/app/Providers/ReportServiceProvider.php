<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Report\PromptExportService;
use App\Services\Report\PromptExportServiceInterface;
use App\Services\Report\ReportBuilder;
use App\Services\Report\ReportBuilderInterface;
use App\Services\Report\ReportExporterInterface;
use App\Services\Report\WordExporter;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportBuilderInterface::class, ReportBuilder::class);
        $this->app->bind(ReportExporterInterface::class, WordExporter::class);
        $this->app->bind(PromptExportServiceInterface::class, PromptExportService::class);
    }
}
