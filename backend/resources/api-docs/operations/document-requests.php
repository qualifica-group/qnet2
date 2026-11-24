<?php

return [
    'GET /document-requests/{documentRequest}' => [
        'summary' => 'Get a document request',
        'description' => 'Returns the request in the same shape as the list. Requires document-requests.view and view on its work order.',
    ],
    'PUT /document-requests/{documentRequest}' => [
        'summary' => 'Edit a document request',
        'description' => 'Changes only due_date, recipient_email, message and send (same rules as the creation; a due date left unchanged is not checked against today). Answers 422 on status when the request is completed, expired or cancelled. The request goes back to delivery_status pending with pending_operation upsert and a job sends the change. Requires document-requests.update and view on the work order.',
    ],
    'GET /document-requests/{documentRequest}/sync-logs' => [
        'summary' => 'List the latest ArchivioSmart calls of a document request',
        'description' => 'Returns the most recent calls (up to 20, newest first): operation (upsert, remind, cancel or refresh), outcome (success, failed or retrying), http_status (null when there was no HTTP response), attempt, message and created_at. Requires document-requests.view and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/remind' => [
        'summary' => 'Remind the customer of a document request',
        'description' => 'Takes no body. Answers 422 on status unless the request is sent or in_progress. Otherwise marks it pending (pending_operation remind) and queues the reminder. Requires document-requests.update and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/cancel' => [
        'summary' => 'Cancel a document request',
        'description' => 'Takes no body. Answers 422 on status when the request is completed or expired; on a request already cancelled it answers 200 and queues nothing. Otherwise marks it pending (pending_operation cancel) and queues the cancellation. Requires document-requests.update and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/refresh' => [
        'summary' => 'Refresh the state of a document request',
        'description' => 'Takes no body. Marks the request pending (pending_operation refresh) and queues a read from ArchivioSmart that replaces the local copy of its sections, items and files. Allowed in any state. Requires document-requests.update and view on the work order.',
    ],
];
