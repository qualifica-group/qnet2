<?php

return [
    'POST /purchase-requests' => [
        'summary' => 'Create a purchase request',
        'description' => 'Creates a purchase request with subject, date, priority, requester, approving manager (function_manager_id, one of the eligible approvers from approver-options, else 422), company and one of its sites, operational site, business function, optional customer and work order, and 1-200 lines (description, quantity, unit price, optional product, supplier, unit, VAT rate). Lines start pending approval; amounts are computed server side. The manager is notified in-app (and by email if enabled) unless they are the author. Returns 201 with the request and permissions. Requires purchase-requests.create.',
    ],
    'GET /purchase-requests/approver-options' => [
        'summary' => 'List the eligible approvers of a purchase request',
        'description' => 'For requester_id and an optional business_function_id, returns who may approve the request: the requester\'s active direct superiors (source reports_to); if none, the active manager of the business function when it is not the requester (source business_function); otherwise an empty list (source none). Each approver has id and name. Requires purchase-requests.create or purchase-requests.update.',
    ],
    'GET /purchase-requests/for-select' => [
        'summary' => 'Search purchase requests for a selector',
        'description' => 'Returns a paginated list of the purchase requests the user can see, newest request date first, each as id, label (subject) and subtitle (requester and date). search matches subject or requester name; offset and limit (default 25, max 100); ids[] appends already selected requests; include_total false skips the total count and reports whether more rows exist. Without purchase-requests.viewAll only requests where the user is requester, manager or author are listed. Any authenticated user.',
    ],
    'GET /purchase-requests/{purchaseRequest}' => [
        'summary' => 'Get a purchase request',
        'description' => 'Returns the purchase request with its priority, requester, manager, totals and lines (supplier, unit, VAT rate, status, approver, linked purchase order), plus the user\'s permissions on it. Requires purchase-requests.view and visibility: purchase-requests.viewAll, or being the requester, manager or author (403 otherwise).',
    ],
    'PUT /purchase-requests/{purchaseRequest}' => [
        'summary' => 'Update a purchase request',
        'description' => 'Partial update: fields not sent keep their value; lines sync only when sent (no id = added, missing = removed). Only pending lines accept new content; approved lines accept supplier, unit price and VAT rate only; removing lines needs purchase-requests.deleteLine and is allowed only for pending or rejected lines (422 otherwise). Changing requester, manager or function re-checks the approver (422). The request closes itself when every line is in a final state. 409 when the request is closed. Returns the request with permissions. Requires purchase-requests.update and visibility.',
    ],
    'DELETE /purchase-requests/{purchaseRequest}' => [
        'summary' => 'Delete a purchase request',
        'description' => 'Deletes the purchase request with its lines, their history and attachments. Returns success with null data. 409 when any line is ordered or received. Requires purchase-requests.delete and visibility on the request.',
    ],
    'POST /purchase-requests/{purchaseRequest}/close' => [
        'summary' => 'Close a purchase request',
        'description' => 'Closes the request by hand; a closed request can no longer be changed or reopened. When some lines are not yet in a final state the closure is forced and reason is required (422 otherwise). 409 when already closed. Returns the request with permissions. Requires purchase-requests.close and visibility on the request.',
    ],
    'GET /purchase-requests/{purchaseRequest}/closure' => [
        'summary' => 'Get the closure state of a purchase request',
        'description' => 'Returns what a close action needs: can_close (request still open and the user holds purchase-requests.close), is_forced (some lines not in a final state, so a reason is needed), the number of such lines and the count of lines per status. Requires purchase-requests.view and visibility on the request.',
    ],
    'POST /purchase-requests/{purchaseRequest}/notify-manager' => [
        'summary' => 'Send the purchase request to its manager again',
        'description' => 'Notifies the approving manager of the request again in-app (and by email if enabled). Returns success with null data and a message naming the manager. 422 when the manager has no email address. Requires purchase-requests.update and visibility on the request.',
    ],
];
