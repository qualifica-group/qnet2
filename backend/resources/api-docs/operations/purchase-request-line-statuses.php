<?php

return [
    'GET /purchase-request-line-statuses/for-select' => [
        'summary' => 'List the purchase request line statuses',
        'description' => 'Returns every purchase request line status in display order, in one page, each as id, label (configured name) and meta with system_key (pending_approval, approved, ordered, received, rejected, on_hold), color, icon and sort_order. Takes no parameters. Any authenticated user.',
    ],
    'POST /purchase-request-line-statuses/reorder' => [
        'summary' => 'Reorder the purchase request line statuses',
        'description' => 'Sets the display order from ordered_ids, which must list every line status exactly once (422 otherwise). Returns each status with its new sort_order. Requires purchase-request-line-statuses.update.',
    ],
    'GET /purchase-request-line-statuses/{purchaseRequestLineStatus}' => [
        'summary' => 'Get a purchase request line status',
        'description' => 'Returns the presentation of one line status (name, color, icon, order, system key) with the user\'s permissions on it. Requires purchase-request-line-statuses.view.',
    ],
    'PUT /purchase-request-line-statuses/{purchaseRequestLineStatus}' => [
        'summary' => 'Rename or restyle a purchase request line status',
        'description' => 'Updates the submitted name (unique), color (badge palette) and icon (badge icon set, nullable). The statuses themselves are fixed: none can be created or deleted, and sort_order and system_key cannot be sent (422). Returns the status with the user\'s permissions. Requires purchase-request-line-statuses.update.',
    ],
];
