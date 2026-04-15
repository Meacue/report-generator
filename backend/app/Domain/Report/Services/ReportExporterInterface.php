<?php

declare(strict_types=1);

namespace App\Domain\Report\Services;

use App\Domain\Report\DTOs\ReportExportData;
use App\Domain\Report\DTOs\ReportExportMonthlyData;

interface ReportExporterInterface
{
    /**
     * Export standard report (weekly / daily / custom) to .docx file.
     *
     * @return string Path to generated file
     */
    public function exportStandard(ReportExportData $reportData): string;

    /**
     * Export monthly report to .docx file.
     *
     * @return string Path to generated file
     */
    public function exportMonthly(ReportExportMonthlyData $reportData): string;
}
