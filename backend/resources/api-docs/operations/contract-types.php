<?php

return [
    'POST /contract-types' => [
        'summary' => 'Create a contract type',
        'description' => 'Creates an entry of the HR contract types catalogue: name (required, unique), optional is_active and sort_order (catalogue order). Returns 201 with the contract type plus permissions, the caller\'s resource, field and action rights on it. Requires contract-types.create.',
    ],
    'GET /contract-types/for-select' => [
        'summary' => 'List contract types for a dropdown',
        'description' => 'Returns active contract types as id and label, ordered by sort order then name, optionally filtered by search (name contains) and paginated with offset and limit. Passing ids[] also returns those types even when deactivated, so a saved value can be displayed. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /contract-types/{contractType}' => [
        'summary' => 'Get a contract type',
        'description' => 'Returns the contract type (name, is_active, sort_order, timestamps) plus permissions, the caller\'s resource, field and action rights on it. Requires contract-types.view.',
    ],
    'PUT /contract-types/{contractType}' => [
        'summary' => 'Update a contract type',
        'description' => 'Updates the contract type\'s name (unique), is_active and sort_order and returns it with the caller\'s permissions. Deactivating keeps it on existing contracts but hides it from the dropdown. Requires contract-types.update.',
    ],
    'DELETE /contract-types/{contractType}' => [
        'summary' => 'Delete a contract type',
        'description' => 'Deletes the contract type. 409 when it is still used by employment contracts or employment profiles, with a message naming the uses and their counts: deactivate it instead. Returns 204 no content. Requires contract-types.delete.',
    ],
];
