<?php

return [
    'POST /field-change-requests' => [
        'summary' => 'Propose a change to a protected field',
        'description' => 'Creates a pending request to change a protected field the caller cannot edit directly (today source_id of request-management and enrollee-management records): resource (table domain), subject_id (record id), field, requested_value (validated like the table cell) and optional reason. Reviewers are notified and the action is logged. 422 when the field is not protected, the caller can already edit it, the value is invalid or unchanged, or a request is already pending; 404 when the record is out of scope. Requires field-change-requests.create and {resource}.view.',
    ],
    'GET /field-change-requests/for-record' => [
        'summary' => 'List the change requests of a record',
        'description' => 'Returns the change requests of one record, identified by the query parameters resource (table domain) and subject_id: pending ones first, then the handled ones, each group newest first, with requester and handler. Callers with field-change-requests.viewAny see all of them; other callers see only their own requests, and get 403 when they have none on that record.',
    ],
    'GET /field-change-requests/{fieldChangeRequest}' => [
        'summary' => 'Get a field change request',
        'description' => 'Returns one change request with its field, current and requested values and labels, reason, status (pending, approved or rejected), requester, handler and handling note. Requires field-change-requests.view, or being its requester.',
    ],
    'POST /field-change-requests/{fieldChangeRequest}/approve' => [
        'summary' => 'Approve a field change request',
        'description' => 'Applies the requested value to the record, acting as the approver, and marks the request approved with the optional note. The requester is notified and the action is logged. 422 when the request is no longer pending or the value is no longer valid; 409 when the field has changed since the request was made (the request stays pending); 403 when the approver cannot edit that field. Requires field-change-requests.manage.',
    ],
    'POST /field-change-requests/{fieldChangeRequest}/reject' => [
        'summary' => 'Reject a field change request',
        'description' => 'Marks a pending request rejected with the optional note, leaving the record untouched. The requester is notified and the action is logged. 422 when the request has already been handled. Requires field-change-requests.manage.',
    ],
];
