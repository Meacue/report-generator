<?php

declare(strict_types=1);

namespace App\Domain\Sync\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class SyncCompleted
{
    use Dispatchable;
}
