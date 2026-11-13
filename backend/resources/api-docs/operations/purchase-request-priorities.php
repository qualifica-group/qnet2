<?php

return [
    'POST /purchase-request-priorities' => [
        'summary' => 'Create a purchase request priority',
        'description' => 'Creates a priority with a unique name, a badge color and optional description, icon, is_active and is_default; it is placed last in the display order. A default priority is the one a new purchase request gets when none is given: setting is_default clears it on the others, and an inactive priority cannot be default (422). Returns 201 with the priority and the user\'s permissions. Requires purchase-request-priorities.create.',
    ],
    'GET /purchase-request-priorities/for-select' => [
        'summary' => 'Search purchase request priorities for a selector',
        'description' => 'Returns a paginated list of active priorities in display order, each as id, label (name) and meta color, icon, is_active and is_default. Supports search on the name, offset and limit (default 25, max 100), include_inactive to include deactivated ones, and ids[] to append already selected priorities. Any authenticated user.',
    ],
    'POST /purchase-request-priorities/reorder' => [
        'summary' => 'Reorder the purchase request priorities',
        'description' => 'Sets the display order from ordered_ids, which must list every priority, active or not, exactly once (422 otherwise). Returns each priority with its new sort_order. Requires purchase-request-priorities.update.',
    ],
    'GET /purchase-request-priorities/{purchaseRequestPriority}' => [
        'summary' => 'Get a purchase request priority',
        'description' => 'Returns the priority with the user\'s permissions on it (resource, fields, actions). Requires purchase-request-priorities.view.',
    ],
    'PUT /purchase-request-priorities/{purchaseRequestPriority}' => [
        'summary' => 'Update a purchase request priority',
        'description' => 'Updates the submitted fields (name, description, color, icon, is_active, is_default); sort_order cannot be sent. Setting is_default clears it on the others; a default priority must stay active (422). Returns the priority with the user\'s permissions. Requires purchase-request-priorities.update.',
    ],
    'DELETE /purchase-request-priorities/{purchaseRequestPriority}' => [
        'summary' => 'Delete a purchase request priority',
        'description' => 'Deletes the priority and answers 204 with no content. 409 when purchase requests still use it. Requires purchase-request-priorities.delete.',
    ],
];
