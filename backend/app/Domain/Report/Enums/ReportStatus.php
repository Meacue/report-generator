<?php

declare(strict_types=1);

namespace App\Domain\Report\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Exported = 'exported';
}
