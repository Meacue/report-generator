<?php

declare(strict_types=1);

namespace App\Domain\Matching\Enums;

enum ResolvedBy: string
{
    case System = 'system';
    case User = 'user';
}
