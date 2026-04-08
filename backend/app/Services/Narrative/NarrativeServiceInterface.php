<?php

declare(strict_types=1);

namespace App\Services\Narrative;

use App\Models\Report;
use App\Models\ReportDay;
use App\Models\ReportTask;

interface NarrativeServiceInterface
{
    /**
     * Generate narratives for all tasks in a report using LLM.
     * On LLM error: set placeholder "[Не удалось сгенерировать описание. Отредактируйте вручную.]"
     */
    public function generateForReport(Report $report): void;

    /**
     * Regenerate narrative for a specific task using LLM.
     * Saves previous narrative to NarrativeHistory before overwriting.
     */
    public function regenerateTask(ReportTask $reportTask): ReportTask;

    /**
     * Regenerate narrative for a specific day (all tasks in that day).
     * Saves previous narrative to NarrativeHistory before overwriting.
     */
    public function regenerateDay(ReportDay $reportDay): ReportDay;

    /**
     * Edit narrative manually with history tracking.
     */
    public function editTaskNarrative(ReportTask $reportTask, string $newNarrative): ReportTask;

    /**
     * Edit day narrative manually with history tracking.
     */
    public function editDayNarrative(ReportDay $reportDay, string $newNarrative): ReportDay;

    /**
     * Undo last edit — restore previous narrative from NarrativeHistory.
     * Returns null if no history exists.
     */
    public function undoTaskNarrative(ReportTask $reportTask): ?ReportTask;

    /**
     * Undo last day narrative edit.
     */
    public function undoDayNarrative(ReportDay $reportDay): ?ReportDay;
}
