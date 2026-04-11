<?php

declare(strict_types=1);

namespace App\Domain\Narrative\Enums;

enum LlmProvider: string
{
    case Claude = 'claude';
    case OpenAI = 'openai';
}
