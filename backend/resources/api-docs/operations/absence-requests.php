<?php

return [
    'POST /absence-requests' => [
        'summary' => 'Create an absence request',
        'description' => 'Creates an absence request as a draft, or files it at once with submit=true. Full day (is_full_day=true) spans starts_on to ends_on; otherwise a single day with day_part (morning or afternoon) or requested_minutes, and the absence type must allow that mode (422 on is_full_day). user_id files it for someone else and is reserved to absence-requests.manageAll. 422 when the period has no working day or overlaps another non-rejected, non-cancelled request of the person. Requires absence-requests.create; returns 201 with the request.',
    ],
    'POST /absence-requests/bulk-decision' => [
        'summary' => 'Approve or reject many absence requests',
        'description' => 'Applies action approve or reject to up to 100 request ids; rejection_reason is required for reject. Each id goes through the same check as the single decision (approver of the request or absence-requests.manageAll, request still submitted) and notifies the same people; a failing id does not block the others. Answers with decided (ids) and failed (id and message, e.g. not found, forbidden, not submitted). Requires absence-requests.viewAny.',
    ],
    'POST /absence-requests/preview' => [
        'summary' => 'Preview an absence request before saving it',
        'description' => 'Takes the same payload as creation (without submit) plus request_id of the draft being edited, and writes nothing. Returns what submission would compute: working days and minutes, non-working days in the period, the balance residual before and after (for balance-deducting types), the notice check, overlapping requests and colleagues absent in the same period. Same validation errors (422) and permissions as creation.',
    ],
    'GET /absence-requests/{absenceRequest}' => [
        'summary' => 'Get an absence request',
        'description' => 'Returns the request with its person, absence type, status (draft, submitted, approved, rejected, cancelled), period and minutes, approvers, decision, attachment summary and the actions the caller may perform (can). Visible to the person, their managers, the request\'s approvers and holders of absence-requests.manageAll; 404 otherwise.',
    ],
    'PATCH /absence-requests/{absenceRequest}' => [
        'summary' => 'Update a draft absence request',
        'description' => 'Replaces the type, mode, period, minutes and note of a draft request, with the same rules as creation. Only the person or a holder of absence-requests.manageAll may edit (403); 409 when the request is no longer a draft; 404 when the request is not visible to the caller.',
    ],
    'DELETE /absence-requests/{absenceRequest}' => [
        'summary' => 'Delete a draft absence request',
        'description' => 'Deletes a request still in draft; returns 204. Only the person or a holder of absence-requests.manageAll (403); 409 once the request has been submitted; 404 when not visible to the caller.',
    ],
    'POST /absence-requests/{absenceRequest}/approve' => [
        'summary' => 'Approve an absence request',
        'description' => 'Moves a submitted request to approved and records who decided and when. Only one of the approvers set at submission or a holder of absence-requests.manageAll may decide, never the person on their own request (403); 409 when the request is not submitted. Notifies the person and the other approvers, with the HR office in copy; the change is activity-logged. Returns the updated request.',
    ],
    'POST /absence-requests/{absenceRequest}/attachment' => [
        'summary' => 'Upload the certificate of an absence request',
        'description' => 'Multipart upload of the single certificate (file: pdf, jpg, jpeg or png), replacing any existing one. The person may attach while the request is draft or submitted; holders of absence-requests.manageAll always (403 otherwise; 404 when not visible). Returns the updated request.',
    ],
    'GET /absence-requests/{absenceRequest}/attachment' => [
        'summary' => 'Download the certificate of an absence request',
        'description' => 'Streams the certificate file attached to the request as a download with its original name. 404 when the request is not visible to the caller or has no attachment.',
    ],
    'DELETE /absence-requests/{absenceRequest}/attachment' => [
        'summary' => 'Remove the certificate of an absence request',
        'description' => 'Deletes the certificate attached to the request; returns 204. Allowed to the person while the request is draft or submitted, and always to holders of absence-requests.manageAll (403 otherwise; 404 when not visible).',
    ],
    'POST /absence-requests/{absenceRequest}/cancel' => [
        'summary' => 'Cancel an absence request',
        'description' => 'Moves a submitted or approved request to cancelled (409 for any other status). Allowed to the person or a holder of absence-requests.manageAll; the person cannot cancel a request generated by a company closure or an approved one already started (403). Notifies the person when someone else cancels and the approvers when it had been filed. Returns the updated request.',
    ],
    'GET /absence-requests/{absenceRequest}/insight' => [
        'summary' => 'Get the decision context of an absence request',
        'description' => 'For whoever reviews a stored request: working days and minutes, non-working days in the period, the person\'s balance residual before and after, the notice check, other overlapping requests and colleagues absent in the same period. 404 when the request is not visible to the caller.',
    ],
    'POST /absence-requests/{absenceRequest}/reject' => [
        'summary' => 'Reject an absence request',
        'description' => 'Moves a submitted request to rejected with the mandatory rejection_reason (max 1000 characters). Only one of its approvers or a holder of absence-requests.manageAll, never the person (403); 409 when not submitted. Notifies the person and the other approvers with the reason. Returns the updated request.',
    ],
    'POST /absence-requests/{absenceRequest}/submit' => [
        'summary' => 'Submit a draft absence request',
        'description' => 'Files a draft: freezes its minutes, re-checks overlaps, flags short notice and snapshots the approvers (422 when the person has none). It becomes submitted and the approvers are notified, or approved at once when the absence type needs no approval. Only the person or a holder of absence-requests.manageAll (403); 409 when not a draft. Returns the updated request.',
    ],
];
