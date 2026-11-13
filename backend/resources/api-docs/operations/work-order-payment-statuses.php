<?php

return [
    'POST /work-order-payment-statuses' => [
        'summary' => 'Create a work order payment status',
        'description' => 'Creates a payment status a work order line can be in: unique name (max 191), optional description, color, is_active and allows_delivery, the flag marking the statuses that mean the goods can be delivered (it drives the delivery notification). sort_order is server-managed (appended last). Returns 201 with the status and the record and field permissions. Requires work-order-payment-statuses.create.',
    ],
    'GET /work-order-payment-statuses/for-select' => [
        'summary' => 'List work order payment statuses for a select',
        'description' => 'Returns active payment statuses as select options (id, label, colour, allows_delivery) in their configured order. search matches the name; offset and limit page the list; ids[] adds the given ids, even inactive, to hydrate an existing value. Responds with items and pagination. Needs only authentication.',
    ],
    'POST /work-order-payment-statuses/reorder' => [
        'summary' => 'Reorder the work order payment statuses',
        'description' => 'Resequences the payment statuses to the order of ordered_ids, which must contain every status id exactly once (422 for duplicates, unknown or missing ids). Returns each id with its new sort_order (system_key is always null). Requires work-order-payment-statuses.update.',
    ],
    'GET /work-order-payment-statuses/{workOrderPaymentStatus}' => [
        'summary' => 'Get a work order payment status',
        'description' => 'Returns the payment status (name, description, colour, sort order, active and allows_delivery flags) with the record and field permissions. Requires work-order-payment-statuses.view.',
    ],
    'PUT /work-order-payment-statuses/{workOrderPaymentStatus}' => [
        'summary' => 'Update a work order payment status',
        'description' => 'Updates the submitted fields of the payment status (partial update, PATCH accepted; sort_order is changed only through the reorder endpoint). Returns the status with the record and field permissions. Requires work-order-payment-statuses.update.',
    ],
    'DELETE /work-order-payment-statuses/{workOrderPaymentStatus}' => [
        'summary' => 'Delete a work order payment status',
        'description' => 'Deletes the payment status and answers 204. 409 when a work order line payment still uses it (deactivate it instead). Requires work-order-payment-statuses.delete.',
    ],
];
