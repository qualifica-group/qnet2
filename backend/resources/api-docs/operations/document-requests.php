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
        'description' => 'Returns the most recent calls (up to 20, newest first): operation (upsert, remind, cancel, refresh, approve, reject or complete), outcome (success, failed or retrying), http_status (null when there was no HTTP response), attempt, message and created_at. Requires document-requests.view and view on the work order.',
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
    'POST /document-requests/{documentRequest}/complete' => [
        'summary' => 'Close a document request early',
        'description' => 'Takes no body. Answers 422 on status unless the request is sent or in_progress; on a request already completed it answers 200 and queues nothing. Otherwise marks it pending (pending_operation complete) and queues the closing in ArchivioSmart, which revokes the portal link. Requires document-requests.update and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/items/{item}/approve' => [
        'summary' => 'Approve the file of an item by hand',
        'description' => 'Overrides the AI outcome of the item. Optional note (max 1000). Answers 422 on status unless the request is sent or in_progress, on item when it is not in the request, on document when it has no uploaded file. Marks the request pending (pending_operation approve) and queues the call, which also sends the name of the QNet user as reviewer. ArchivioSmart may not support it yet: the request then shows delivery_status failed. Requires document-requests.review and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/items/{item}/reject' => [
        'summary' => 'Reject the file of an item by hand',
        'description' => 'Same rules as approve, but the note is required (max 1000, 422 on note when missing). The item becomes rejected_manually once ArchivioSmart answers (pending_operation reject). Requires document-requests.review and view on the work order.',
    ],
    'GET /document-requests/{documentRequest}/documents/{document}/download' => [
        'summary' => 'Download an uploaded file of a document request',
        'description' => 'Streams the file with its original name; QNet asks ArchivioSmart for a temporary link and relays the bytes, nothing is stored. Answers 404 when the file is not in the request or ArchivioSmart does not know it, and 502 when ArchivioSmart cannot deliver it. Requires document-requests.view and view on the work order.',
    ],
];
