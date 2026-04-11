<?php

declare(strict_types=1);

namespace App\Domain\Report\Enums;

enum ReportType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';
}
