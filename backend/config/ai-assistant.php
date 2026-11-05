<?php

// Types the drivers can forward; AI_ASSISTANT_ACCEPTED_MIMES can only narrow this list (a local
// model may read images but not PDFs), never widen it.
$supportedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

return [

    /*
    |--------------------------------------------------------------------------
    | Surfaces (spec 0347, D-16)
    |--------------------------------------------------------------------------
    |
    | The floating chat and the /ai-assistant page are switched on separately
    | and are both OFF by default. With both off every /api/ai-assistant
    | endpoint except /config answers 404; with the page off its menu item is
    | hidden. The `ai-assistant.use` permission is still required on top.
    |
    */

    'floating_enabled' => (bool) env('AI_ASSISTANT_FLOATING_ENABLED', false),
    'page_enabled' => (bool) env('AI_ASSISTANT_PAGE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Provider driver (spec 0347, D-2)
    |--------------------------------------------------------------------------
    |
    | "openai" (any OpenAI-compatible /v1/chat/completions server: OpenAI, or a
    | local LLM such as LM Studio / Ollama via AI_ASSISTANT_OPENAI_BASE_URL, where
    | the api key may stay empty) or "anthropic" (Messages API). null = assistant
    | not configured. Provider keys live only in .env, never in responses or logs.
    |
    */

    'driver' => env('AI_ASSISTANT_DRIVER'),

    'openai' => [
        'base_url' => env('AI_ASSISTANT_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('AI_ASSISTANT_OPENAI_API_KEY'),
        'model' => env('AI_ASSISTANT_OPENAI_MODEL', 'gpt-4.1-mini'),
    ],

    'anthropic' => [
        'base_url' => env('AI_ASSISTANT_ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'api_key' => env('AI_ASSISTANT_ANTHROPIC_API_KEY'),
        'model' => env('AI_ASSISTANT_ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'version' => env('AI_ASSISTANT_ANTHROPIC_VERSION', '2023-06-01'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation and limits
    |--------------------------------------------------------------------------
    */

    'max_output_tokens' => (int) env('AI_ASSISTANT_MAX_OUTPUT_TOKENS', 2048),
    'temperature' => env('AI_ASSISTANT_TEMPERATURE') !== null ? (float) env('AI_ASSISTANT_TEMPERATURE') : null,
    'timeout_seconds' => (int) env('AI_ASSISTANT_TIMEOUT_SECONDS', 120),
    'connect_timeout_seconds' => (int) env('AI_ASSISTANT_CONNECT_TIMEOUT_SECONDS', 10),

    'history_window' => (int) env('AI_ASSISTANT_HISTORY_WINDOW', 20),
    'max_message_chars' => (int) env('AI_ASSISTANT_MAX_MESSAGE_CHARS', 8000),
    'guide_max_chars' => (int) env('AI_ASSISTANT_GUIDE_MAX_CHARS', 40000),
    'max_mentions' => (int) env('AI_ASSISTANT_MAX_MENTIONS', 5),
    'mention_max_chars' => (int) env('AI_ASSISTANT_MENTION_MAX_CHARS', 8000),

    'max_files' => (int) env('AI_ASSISTANT_MAX_FILES', 4),
    'max_file_size_kb' => (int) env('AI_ASSISTANT_MAX_FILE_SIZE_KB', 10240),
    'accepted_mimes' => array_values(array_intersect(
        $supportedMimes,
        array_map('trim', explode(',', (string) (env('AI_ASSISTANT_ACCEPTED_MIMES') ?: implode(',', $supportedMimes)))),
    )),

    'retention_days' => (int) env('AI_ASSISTANT_RETENTION_DAYS', 30),

];
