<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Events;

use App\Domain\Report\Models\ReportDay;
use App\Domain\Report\Models\ReportTask;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class NarrativeEdited
{
    use Dispatchable;

    public function __construct(
        public ReportTask|ReportDay $narratable,
        public string $previousNarrative,
    ) {
    }
}
