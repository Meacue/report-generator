<?php

declare(strict_types=1);

namespace App\Domain\Report\Enums;

enum ReportDaySource: string
{
    case Commits = 'commits';
    case Bitrix24Fallback = 'bitrix24_fallback';
    case Manual = 'manual';
}
