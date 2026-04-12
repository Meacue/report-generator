<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Report\Services\PromptExportServiceInterface;
use App\Domain\Report\Services\ReportExporterInterface;
use App\Infrastructure\Report\PromptExportService;
use App\Infrastructure\Report\WordExporter;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportExporterInterface::class, WordExporter::class);
        $this->app->bind(PromptExportServiceInterface::class, PromptExportService::class);
    }
}
