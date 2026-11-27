<?php

return [
    // Checklist sync outcomes (stored in sync_error and in the log).
    'not_configured' => 'ArchivioSmart connection not configured',
    'unauthorized' => 'Invalid ArchivioSmart token',
    'conflict' => 'A checklist with this identifier already exists in ArchivioSmart and was not created by QNet 2',
    'category_conflict' => 'A category with this identifier already exists in ArchivioSmart and was not created by QNet 2',
    'category_not_synced' => 'Category not synced: :reason',
    'validation_failed' => 'ArchivioSmart rejected the checklist',
    'server_error' => 'ArchivioSmart responded with a server error (:status)',
    'network_error' => 'Unable to reach ArchivioSmart',
    'unexpected_status' => 'Unexpected response from ArchivioSmart (:status)',
    'job_generic_failure' => 'ArchivioSmart sync stopped unexpectedly',

    // Connection test.
    'connection_not_configured' => 'ArchivioSmart connection not configured',
    'connection_ok' => 'ArchivioSmart connection successful',
    'connection_failed' => 'ArchivioSmart connection failed (:status)',

    // Document requests (spec 0428).
    'request_conflict' => 'ArchivioSmart refused the request: a request with this identifier already exists',
    'request_status_closed' => 'The request can no longer be edited',
    'request_not_remindable' => 'Only a sent or in progress request can be reminded',
    'request_not_cancellable' => 'A completed or expired request cannot be cancelled',
    'request_not_reviewable' => 'Only a sent or in progress request can be worked on',
    'request_not_completable' => 'A draft, expired or cancelled request cannot be closed',
    'request_item_not_found' => 'The item does not belong to the request',
    'request_item_without_document' => 'The item has no uploaded file',
    'request_action_unavailable' => 'ArchivioSmart does not support this action yet',
    'request_never_delivered' => 'The request never reached ArchivioSmart: fix it with Edit and send it again, or cancel it',
    'request_rules_not_refreshable' => 'Only the rules of a draft, sent or in progress request can be refreshed',
    'request_item_not_stoppable' => 'The item has no file waiting for the AI validation',
    'checklist_not_synced' => 'The checklist has not been sent to ArchivioSmart yet: the rules are refreshed after it is sent',
    'document_download_failed' => 'The file could not be downloaded from ArchivioSmart',
    'document_not_found' => 'File not found',
    'webhook_not_found' => 'Not found',
    'webhook_invalid_signature' => 'Invalid signature',

    'attributes' => [
        'base_url' => 'base URL',
        'token' => 'token',
        'due_date' => 'due date',
        'recipient_email' => 'recipient email',
        'message' => 'message',
        'document_checklist_id' => 'checklist',
        'webhook_secret' => 'webhook secret',
        'item_ids' => 'items',
    ],
];
