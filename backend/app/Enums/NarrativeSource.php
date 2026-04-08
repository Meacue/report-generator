<?php

declare(strict_types=1);

namespace App\Enums;

enum NarrativeSource: string
{
    case ManualEdit = 'manual_edit';
    case LlmRegeneration = 'llm_regeneration';
}
