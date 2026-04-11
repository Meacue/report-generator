<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportTask;

final readonly class UndoTaskNarrative
{
    public function __construct(
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportTask $reportTask): ?ReportTask
    {
        return $this->support->undoNarrative($reportTask);
    }
}
