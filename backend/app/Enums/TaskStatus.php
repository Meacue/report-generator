<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Completed = 'completed';
    case InProgress = 'in_progress';
}
