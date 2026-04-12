<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Listeners;

use App\Domain\Narrative\Actions\GenerateNarrativesForReport;
use App\Domain\Report\Events\ReportGenerated;

final readonly class GenerateNarrativesOnReportGenerated
{
    public function __construct(
        private GenerateNarrativesForReport $generateNarratives,
    ) {
    }

    public function handle(ReportGenerated $event): void
    {
        ($this->generateNarratives)($event->report);
    }
}
