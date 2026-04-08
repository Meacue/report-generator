<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncStatus: string
{
    case InProgress = 'in_progress';
    case Success = 'success';
    case Failed = 'failed';
}
