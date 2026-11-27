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
        'description' => 'Returns the most recent calls (up to 20, newest first): operation (upsert, remind, cancel, refresh, approve, reject, complete, refresh_rules, revalidate or stop_validation), outcome (success, failed or retrying), http_status (null when there was no HTTP response), attempt, message and created_at. Requires document-requests.view and view on the work order.',
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
    'POST /document-requests/{documentRequest}/refresh-rules' => [
        'summary' => 'Copy the current checklist rules into a document request',
        'description' => 'Takes no body. ArchivioSmart copies description, allowed file types, maximum size, validation prompt and validation rules of each item from the current checklist; outcomes already given do not change. Answers 422 on status unless the request is draft, sent or in_progress. Marks the request pending (pending_operation refresh_rules) and queues the call; the ids of the changed items come back in rules_updated_item_ids. Requires document-requests.update and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/revalidate' => [
        'summary' => 'Validate items again with the AI',
        'description' => 'Body: item_ids (1 to 100 distinct item ids). ArchivioSmart validates again all the current files of each item with its current rules; a manual decision on the item is dropped. Answers 422 on status unless the request is sent or in_progress, on item when an item is not in the request, on document when an item has no file. Queues one call per item (pending_operation revalidate) and removes the items from rules_updated_item_ids. Requires document-requests.review and view on the work order.',
    ],
    'POST /document-requests/{documentRequest}/items/{item}/stop-validation' => [
        'summary' => 'Stop the AI validation of an item',
        'description' => 'Takes no body. The files of the item still waiting for the AI go to manual review, then approve or reject decides. Answers 422 on status unless the request is sent or in_progress, on item when it is not in the request, on document unless the item is uploaded or validating. Queues the call with the name of the QNet user as reviewer (pending_operation stop_validation). Requires document-requests.review and view on the work order.',
    ],
    'GET /document-requests/{documentRequest}/documents/{document}/download' => [
        'summary' => 'Download an uploaded file of a document request',
        'description' => 'Streams the file with its original name; QNet asks ArchivioSmart for a temporary link and relays the bytes, nothing is stored. Answers 404 when the file is not in the request or ArchivioSmart does not know it, and 502 when ArchivioSmart cannot deliver it. Requires document-requests.view and view on the work order.',
    ],
];
