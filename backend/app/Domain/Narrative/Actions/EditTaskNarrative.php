<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Report\Models\ReportTask;

final readonly class EditTaskNarrative
{
    public function __invoke(ReportTask $reportTask, string $newNarrative): ReportTask
    {
        $previousNarrative = $reportTask->narrative ?? '';

        $reportTask->editNarrative($newNarrative);

        NarrativeEdited::dispatch($reportTask, $previousNarrative);

        return $reportTask->refresh();
    }
}
