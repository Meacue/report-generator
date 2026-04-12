<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Contracts;

use App\Domain\Narrative\Enums\NarrativeSource;

interface HasNarrativeSource
{
    public function source(): NarrativeSource;
}
