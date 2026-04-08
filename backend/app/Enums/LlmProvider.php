<?php

declare(strict_types=1);

namespace App\Enums;

enum LlmProvider: string
{
    case Claude = 'claude';
    case OpenAI = 'openai';
}
