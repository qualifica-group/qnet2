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

    /*
    |--------------------------------------------------------------------------
    | Agent turn (spec 0433)
    |--------------------------------------------------------------------------
    |
    | The turn runs in a queued job on the `ai-assistant` queue, one attempt.
    | Model and routing are the initial values read here and nowhere else; the
    | settings page (ticket 08) replaces this source. The OpenRouter key and
    | base URL are the ones of the `openrouter` section above. The job timeout
    | is derived from these values and must stay under the queue `retry_after`.
    |
    */

    'agent' => [
        'model' => env('AI_ASSISTANT_AGENT_MODEL', 'deepseek/deepseek-v4-flash-0731'),

        'routing' => [
            'zdr' => (bool) env('AI_ASSISTANT_AGENT_ZDR', true),
            'data_collection' => env('AI_ASSISTANT_AGENT_DATA_COLLECTION') === 'allow' ? 'allow' : 'deny',
            'require_parameters' => (bool) env('AI_ASSISTANT_AGENT_REQUIRE_PARAMETERS', true),
            'sort' => env('AI_ASSISTANT_AGENT_SORT', 'throughput'),
        ],

        // Caps per turn: a cap reached closes the turn with a fixed text.
        'max_model_calls' => (int) env('AI_ASSISTANT_AGENT_MAX_MODEL_CALLS', 8),
        'max_tool_calls' => (int) env('AI_ASSISTANT_AGENT_MAX_TOOL_CALLS', 12),
        'max_turn_seconds' => (int) env('AI_ASSISTANT_AGENT_MAX_TURN_SECONDS', 600),
        'max_output_tokens' => (int) env('AI_ASSISTANT_AGENT_MAX_OUTPUT_TOKENS', 4096),

        // A turn left queued or running longer than the longest job plus this margin is closed as failed.
        'stale_margin_seconds' => (int) env('AI_ASSISTANT_AGENT_STALE_MARGIN_SECONDS', 120),

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

];
