<?php

return [
    'default' => env('LLM_PROVIDER', 'claude'),

    'providers' => [
        'claude' => [
            'api_key'    => env('LLM_API_KEY'),
            'model'      => env('LLM_CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => (int) env('LLM_MAX_TOKENS', 1024),
        ],
        'openai' => [
            'api_key'    => env('LLM_API_KEY'),
            'model'      => env('LLM_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => (int) env('LLM_MAX_TOKENS', 1024),
        ],
    ],

    'default_system_prompt' => 'Ты — ассистент для генерации отчётов разработчика. Пиши на русском языке в деловом стиле. Каждое описание — 2-3 предложения.',
];
