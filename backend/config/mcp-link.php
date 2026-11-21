<?php

/*
|--------------------------------------------------------------------------
| MCP link module
|--------------------------------------------------------------------------
|
| An MCP connector, authenticated with its API client key, opens a long-lived
| link credential for a user and exchanges it for short-lived user tokens.
| Every duration and limit is configuration with a default value.
|
*/

return [
    // Lifetime of a link credential, in days.
    'link_ttl_days' => (int) env('MCP_LINK_TTL_DAYS', 365),

    // Lifetime, in minutes, of a user token issued by an exchange (never beyond the link).
    'access_token_ttl_minutes' => (int) env('MCP_LINK_ACCESS_TOKEN_TTL_MINUTES', 60),

    // Failed openings per account (normalised email) before the route answers 429.
    'open_attempts' => [
        'max' => (int) env('MCP_LINK_OPEN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('MCP_LINK_OPEN_DECAY_MINUTES', 15),
    ],

    // Commit of QNET2 in service, set by the release; null when not provided.
    'release_commit' => env('MCP_LINK_RELEASE_COMMIT'),
];
