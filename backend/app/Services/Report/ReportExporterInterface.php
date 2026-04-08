<?php

declare(strict_types=1);

namespace App\Services\Report;

interface ReportExporterInterface
{
    /**
     * Export report to .docx file.
     *
     * @param  array<string, mixed>  $reportData
     * @return string Path to generated file
     */
    public function export(array $reportData): string;
}
