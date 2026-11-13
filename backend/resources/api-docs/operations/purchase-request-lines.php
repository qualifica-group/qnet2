<?php

return [
    'POST /purchase-request-lines/purchase-orders' => [
        'summary' => 'Generate purchase orders from approved request lines',
        'description' => 'Turns approved purchase request lines (line_ids, max 200) into draft purchase orders, one per supplier, company, company site and operational site, dated order_date (default today), with the active cost_type_id on every line and the optional payment_method_id. All or nothing: lines move to ordered (logged) with the order code as reference; fully worked requests close. 403 without purchase-requests.fulfill or manageStatuses on a line\'s request, 409 on a closed request, 422 for a line not approved, without supplier or already ordered. Returns 201 with the created orders and the closed request ids. Requires purchase-orders.create.',
    ],
    'POST /purchase-request-lines/status' => [
        'summary' => 'Change the status of purchase request lines',
        'description' => 'Moves lines (line_ids, max 200, any request) to to_status: pending_approval, approved, ordered, received, rejected or on_hold. reason is required for rejected and on_hold; suppliers (line_id, supplier_id pairs) only with ordered, for lines lacking one. Transitions depend on the actor\'s role on each request: function manager (never on their own request), purchase-requests.fulfill, purchase-requests.manageStatuses. All or nothing, logged; fully worked requests close. 403 out of reach, 409 closed request, 422 transition not allowed. Returns updated_count and closed_purchase_request_ids.',
    ],
    'POST /purchase-request-lines/supplier' => [
        'summary' => 'Assign a supplier to purchase request lines',
        'description' => 'Sets the same supplier registry (supplier_id, must be a supplier) on many lines (line_ids, max 200). A pending line needs the right to update its request; an approved line needs purchase-requests.fulfill or purchase-requests.manageStatuses; lines in any other status are frozen. All or nothing: 403 when a line is out of reach, 409 on a closed request, 422 per line whose supplier can no longer change. Returns updated_count.',
    ],
    'GET /purchase-request-lines/{purchaseRequestLine}/status-logs' => [
        'summary' => 'List the status history of a purchase request line',
        'description' => 'Returns every status change of the line, most recent first: who made it, from and to status, the reason and whether it was part of a mass change. Requires purchase-requests.view on the line\'s request.',
    ],
];
