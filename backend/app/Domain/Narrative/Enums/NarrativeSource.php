<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Enums;

enum NarrativeSource: string
{
    case ManualEdit = 'manual_edit';
    case LlmRegeneration = 'llm_regeneration';
}
