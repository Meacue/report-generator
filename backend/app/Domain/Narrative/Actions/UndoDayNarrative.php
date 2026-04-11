<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Actions;

use App\Domain\Narrative\Services\NarrativeSupport;
use App\Domain\Report\Models\ReportDay;

final readonly class UndoDayNarrative
{
    public function __construct(
        private NarrativeSupport $support,
    ) {
    }

    public function __invoke(ReportDay $reportDay): ?ReportDay
    {
        return $this->support->undoNarrative($reportDay);
    }
}
