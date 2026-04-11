<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;

final readonly class EditTaskNarrative
{
    public function __construct(
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportTask $reportTask, string $newNarrative): ReportTask
    {
        $this->support->saveHistory($reportTask, NarrativeSource::ManualEdit);

        $reportTask->update([
            'narrative' => $newNarrative,
            'is_edited' => true,
        ]);

        return $reportTask->refresh();
    }
}
