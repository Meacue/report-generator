<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Report;

interface PromptExportServiceInterface
{
    /**
     * Build a text file content with the system prompt + report summary data.
     * Designed to be pasted into AI chatbots for manual narrative generation.
     */
    public function buildPromptFile(Report $report): string;
}
