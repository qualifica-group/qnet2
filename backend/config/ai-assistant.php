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
    | OpenRouter (spec 0433, D-13)
    |--------------------------------------------------------------------------
    |
    | The only model provider of the assistant (the drivers for OpenAI and
    | Anthropic of spec 0347 are gone). site_url / app_name are the optional
    | attribution headers (HTTP-Referer / X-Title). The key lives only in .env,
    | never in responses or logs. Model and routing: see `agent` below.
    |
    */

    'openrouter' => [
        'base_url' => env('AI_ASSISTANT_OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('AI_ASSISTANT_OPENROUTER_API_KEY'),
        'site_url' => env('AI_ASSISTANT_OPENROUTER_SITE_URL') ?: env('FRONTEND_URL'),
        'app_name' => env('AI_ASSISTANT_OPENROUTER_APP_NAME') ?: env('APP_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    // Read and connect timeouts of every call to the model.
    'timeout_seconds' => (int) env('AI_ASSISTANT_TIMEOUT_SECONDS', 120),
    'connect_timeout_seconds' => (int) env('AI_ASSISTANT_CONNECT_TIMEOUT_SECONDS', 10),

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

    // Memory of the conversation (spec 0433, D-5). The old part is summarised when the request passes
    // `summary_threshold_percent` of the model's context window; the last `keep_recent_turns` turns
    // always stay whole; after `max_summaries` summaries a new conversation is proposed.
    // `bytes_per_token` is the prudent divisor of the token estimate (2.0 overestimates on purpose).
    'memory' => [
        'summary_threshold_percent' => (int) env('AI_ASSISTANT_SUMMARY_THRESHOLD_PERCENT', 70),
        'keep_recent_turns' => (int) env('AI_ASSISTANT_KEEP_RECENT_TURNS', 2),
        'max_summaries' => (int) env('AI_ASSISTANT_MAX_SUMMARIES', 3),
        'summary_max_words' => (int) env('AI_ASSISTANT_SUMMARY_MAX_WORDS', 300),
        'bytes_per_token' => (float) env('AI_ASSISTANT_BYTES_PER_TOKEN', 2.0),
        // Window used when OpenRouter does not list the model or the list is unreachable: small on purpose.
        'fallback_context_tokens' => (int) env('AI_ASSISTANT_FALLBACK_CONTEXT_TOKENS', 32000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent turn (spec 0433)
    |--------------------------------------------------------------------------
    |
    | The turn runs in a queued job on the `ai-assistant` queue, one attempt.
    | Model, routing and additional instructions come from the settings row
    | (`settings` below, AiAssistantSettingsService::effective()). The OpenRouter
    | key and base URL are the ones of the `openrouter` section above. The job
    | timeout is derived from these values and must stay under the queue `retry_after`.
    |
    */

    'agent' => [
        // Caps per turn: a cap reached closes the turn with a fixed text.
        'max_model_calls' => (int) env('AI_ASSISTANT_AGENT_MAX_MODEL_CALLS', 8),
        'max_tool_calls' => (int) env('AI_ASSISTANT_AGENT_MAX_TOOL_CALLS', 12),
        'max_turn_seconds' => (int) env('AI_ASSISTANT_AGENT_MAX_TURN_SECONDS', 600),
        'max_output_tokens' => (int) env('AI_ASSISTANT_AGENT_MAX_OUTPUT_TOKENS', 4096),

        // A turn left queued or running longer than the longest job plus this margin is closed as failed.
        'stale_margin_seconds' => (int) env('AI_ASSISTANT_AGENT_STALE_MARGIN_SECONDS', 120),

        // Life of a write preview: the one of the connector (10 minutes) minus a margin, so QNET2 always
        // expires first (the stricter side) and the delayed job of the expiry fires at that instant.
        'preview_ttl_seconds' => (int) env('AI_ASSISTANT_PREVIEW_TTL_SECONDS', 600),
        'preview_margin_seconds' => (int) env('AI_ASSISTANT_PREVIEW_MARGIN_SECONDS', 5),

        // How often the browser reads the turn state, reported by GET /config.
        'poll_interval_ms' => (int) env('AI_ASSISTANT_AGENT_POLL_INTERVAL_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connector entry of the assistant (spec 0433, internal_contract)
    |--------------------------------------------------------------------------
    |
    | Service-to-service MCP entry of the connector. `url` is where QNET2 reaches
    | it (127.0.0.1 in production); `host` is the public host the connector
    | accepts in the Host header. The secret is shared with the connector and
    | never written to logs, responses or the activity log.
    |
    */

    'connector' => [
        'url' => env('AI_ASSISTANT_CONNECTOR_URL', 'http://127.0.0.1:3000'),
        'host' => env('AI_ASSISTANT_CONNECTOR_HOST'),
        'secret' => env('AI_ASSISTANT_CONNECTOR_SECRET'),
        'protocol_version' => '2025-06-18',
        'timeout_seconds' => (int) env('AI_ASSISTANT_CONNECTOR_TIMEOUT_SECONDS', 90),
        'connect_timeout_seconds' => (int) env('AI_ASSISTANT_CONNECTOR_CONNECT_TIMEOUT_SECONDS', 5),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Costs and spending caps (spec 0433, D-7)
    |--------------------------------------------------------------------------
    |
    | input_margin_tokens: fixed allowance for the tokens of the chat format, added to the UTF-8
    | bytes of the request when the cost of a call is reserved (a token is never shorter than one
    | byte). reservation_ttl_minutes: an open reservation older than this is dropped, so a crashed
    | worker cannot hold the cap forever. report_max_days: longest period the costs page may ask for.
    |
    */

    'costs' => [
        'input_margin_tokens' => (int) env('AI_ASSISTANT_COST_INPUT_MARGIN_TOKENS', 1000),
        'reservation_ttl_minutes' => (int) env('AI_ASSISTANT_COST_RESERVATION_TTL_MINUTES', 30),
        'report_max_days' => (int) env('AI_ASSISTANT_COST_REPORT_MAX_DAYS', 366),
    ],

];
