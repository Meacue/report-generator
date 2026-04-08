<?php

declare(strict_types=1);

namespace App\Enums;

enum ConfidenceLevel: string
{
    case Auto = 'auto';
    case Probable = 'probable';
    case None = 'none';
}
