<?php

declare(strict_types=1);

namespace App\Domain\Report\Events;

use App\Domain\Report\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ReportGenerated
{
    use Dispatchable;

    public function __construct(
        public Report $report,
    ) {
    }
}
