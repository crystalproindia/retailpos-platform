<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'enabled' => env('AI_PROVIDER_ENABLED', false),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'endpoint' => env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/responses'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 12),
    ],
    'max_question_length' => 500,
    'max_output_characters' => 4000,
    'conversation_turn_limit' => 6,
    'context_cache_seconds' => 60,
    'requests_per_minute' => 10,
];
