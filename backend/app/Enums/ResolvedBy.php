<?php

declare(strict_types=1);

namespace App\Enums;

enum ResolvedBy: string
{
    case System = 'system';
    case User = 'user';
}
