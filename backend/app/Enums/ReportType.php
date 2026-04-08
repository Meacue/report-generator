<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Custom = 'custom';
}
