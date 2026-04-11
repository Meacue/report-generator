<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

enum SyncStatus: string
{
    case InProgress = 'in_progress';
    case Success = 'success';
    case Failed = 'failed';
}
