<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

enum SyncStep: string
{
    case GitLab = 'gitlab';
    case Bitrix24 = 'bitrix24';
    case Matching = 'matching';
}
