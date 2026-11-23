<?php

/*
| ArchivioSmart integration (spec 0403). Numbers live here, never inline.
*/
return [
    // HTTP timeouts in seconds: the call runs in a queue worker, keep it short.
    'timeout' => 15,
    'connect_timeout' => 5,

    // Job attempts and wait (seconds) before each retry on network/5xx errors.
    'tries' => 5,
    'backoff' => [10, 30, 90, 300],

    // Wait when a 429 comes without a Retry-After header.
    'retry_after_fallback' => 60,

    // Checklist item limits, mirrored by the FormRequest and the frontend schema.
    'max_file_size_kb' => 20480,
    'default_file_size_kb' => 10240,
    'max_items' => 200,
    'max_files' => 10,
    'max_age_days' => 3650,

    // Sync log rows shown in the detail, and truncation of stored messages.
    'sync_log_display_limit' => 20,
    'error_message_max_length' => 1000,

    // Maximum field lengths (ArchivioSmart contract).
    'max_length' => [
        'name' => 255,
        'code' => 50,
        'description' => 2000,
        'section' => 255,
        'validation_prompt' => 5000,
    ],
];
