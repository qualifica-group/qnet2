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
    | the api key may stay empty), "anthropic" (Messages API) or "openrouter"
    | (OpenRouter gateway, D-17). null = assistant not configured. Provider keys
    | live only in .env, never in responses or logs.
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

    // OpenRouter (D-17): model id is "<vendor>/<model>". site_url / app_name are
    // the optional attribution headers (HTTP-Referer / X-Title). data_collection
    // "deny" (default) routes only to upstream providers that do not store or
    // train on prompts; any value other than "allow" counts as "deny".
    'openrouter' => [
        'base_url' => env('AI_ASSISTANT_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('AI_ASSISTANT_OPENROUTER_API_KEY'),
        'model' => env('AI_ASSISTANT_OPENROUTER_MODEL', 'openai/gpt-4.1-mini'),
        'site_url' => env('AI_ASSISTANT_OPENROUTER_SITE_URL') ?: env('FRONTEND_URL'),
        'app_name' => env('AI_ASSISTANT_OPENROUTER_APP_NAME') ?: env('APP_NAME'),
        'data_collection' => env('AI_ASSISTANT_OPENROUTER_DATA_COLLECTION') === 'allow' ? 'allow' : 'deny',
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

    // Initial value of the "model accepts attachments" criterion (spec 0433): text-only until the model settings exist.
    'model_accepts_attachments' => (bool) env('AI_ASSISTANT_MODEL_ACCEPTS_ATTACHMENTS', false),

    'max_files' => (int) env('AI_ASSISTANT_MAX_FILES', 4),
    'max_file_size_kb' => (int) env('AI_ASSISTANT_MAX_FILE_SIZE_KB', 10240),
    'accepted_mimes' => array_values(array_intersect(
        $supportedMimes,
        array_map('trim', explode(',', (string) (env('AI_ASSISTANT_ACCEPTED_MIMES') ?: implode(',', $supportedMimes)))),
    )),

    'retention_days' => (int) env('AI_ASSISTANT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Super-admin settings (spec 0433)
    |--------------------------------------------------------------------------
    |
    | Initial values of the single settings row (model, routing) and the limits
    | of the settings page. The OpenRouter key stays in .env above.
    |
    */

    'settings' => [
        'default_model' => env('AI_ASSISTANT_DEFAULT_MODEL', 'deepseek/deepseek-v4-flash-0731'),
        'default_routing' => [
            'zdr' => true,
            'data_collection' => 'deny',
            'require_parameters' => true,
            'sort' => 'throughput',
        ],
        'instructions_max_chars' => (int) env('AI_ASSISTANT_INSTRUCTIONS_MAX_CHARS', 8000),
        'models_cache_seconds' => (int) env('AI_ASSISTANT_MODELS_CACHE_SECONDS', 300),
        'catalog_timeout_seconds' => (int) env('AI_ASSISTANT_CATALOG_TIMEOUT_SECONDS', 10),
    ],

];
