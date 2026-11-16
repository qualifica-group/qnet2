<?php

return [
    'GET /purchase-order-statuses/for-select' => [
        'summary' => 'List all purchase order statuses',
        'description' => 'Returns the whole fixed catalogue of purchase order statuses in display order, one per status (draft, in_validation, approved, sent_to_supplier, partially_fulfilled, fulfilled, cancelled): id, label = display name and meta with system_key (the value purchase orders expose as status), color, icon and sort_order. Takes no parameters; response holds items and pagination. Needs only authentication.',
    ],
    'POST /purchase-order-statuses/reorder' => [
        'summary' => 'Reorder the purchase order statuses',
        'description' => 'Sets the display order of the purchase order statuses: ordered_ids must list every status id exactly once, in the new order. Returns the list as id and sort_order. 422 when the set is incomplete or has duplicates. Requires purchase-order-statuses.update.',
    ],
    'GET /purchase-order-statuses/{purchaseOrderStatus}' => [
        'summary' => 'Get a purchase order status',
        'description' => 'Returns the presentation of one purchase order status (system_key, name, color, icon, sort_order) with the caller\'s permissions on it. Requires purchase-order-statuses.view.',
    ],
    'PUT /purchase-order-statuses/{purchaseOrderStatus}' => [
        'summary' => 'Update the presentation of a purchase order status',
        'description' => 'Changes only the display name (unique), color and icon of a purchase order status, chosen from the allowed badge tokens; the status itself and its behaviour do not change. Statuses cannot be created or deleted, and sending sort_order or system_key is refused with 422. Requires purchase-order-statuses.update.',
    ],
];
