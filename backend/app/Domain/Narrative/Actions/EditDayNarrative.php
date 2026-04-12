<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Enums\NarrativeSource;
use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportDay;

final readonly class EditDayNarrative
{
    public function __construct(
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportDay $reportDay, string $newNarrative): ReportDay
    {
        $this->support->saveHistory($reportDay, NarrativeSource::ManualEdit);

        $reportDay->editNarrative($newNarrative);

        return $reportDay->refresh();
    }
}
