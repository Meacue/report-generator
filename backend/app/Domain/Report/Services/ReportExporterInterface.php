<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Report\DTOs\ReportExportData;

interface ReportExporterInterface
{
    /**
     * Export report to .docx file.
     *
     * @return string Path to generated file
     */
    public function export(ReportExportData $reportData): string;
}
