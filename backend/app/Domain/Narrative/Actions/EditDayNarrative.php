<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Events\NarrativeEdited;
use App\Domain\Report\Models\ReportDay;

final readonly class EditDayNarrative
{
    public function __invoke(ReportDay $reportDay, string $newNarrative): ReportDay
    {
        $previousNarrative = $reportDay->narrative ?? '';

        $reportDay->editNarrative($newNarrative);

        NarrativeEdited::dispatch($reportDay, $previousNarrative);

        return $reportDay->refresh();
    }
}
