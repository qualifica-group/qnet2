<?php

return [
    'POST /contract-statuses' => [
        'summary' => 'Create a contract status',
        'description' => 'Creates a contract status with a unique name, a color and a group (open, pending, closed_won, closed_lost); it is placed after the existing custom statuses and before the closing system ones. Sending is_default true makes it the only default status and clears the flag elsewhere; a default status must be active (422 otherwise). Returns 201 with the status and the user\'s permissions on it. Requires contract-statuses.create.',
    ],
    'GET /contract-statuses/for-select' => [
        'summary' => 'Search contract statuses for a selector',
        'description' => 'Returns a paginated list of active contract statuses in display order, each as id, label (name) and meta.system_key (null for custom statuses). Supports search on the name, offset and limit (default 25, max 100), status_groups[] to keep only some groups, include_inactive to include deactivated ones, and ids[] to append already selected statuses even when filtered out. Any authenticated user.',
    ],
    'POST /contract-statuses/reorder' => [
        'summary' => 'Reorder the custom contract statuses',
        'description' => 'Sets the display order of the custom contract statuses from ordered_ids, which must list every custom status exactly once and no system status (422 otherwise); system statuses keep their fixed place at the head and tail. Returns every status with its new sort_order and system_key. Requires contract-statuses.update.',
    ],
    'GET /contract-statuses/{contractStatus}' => [
        'summary' => 'Get a contract status',
        'description' => 'Returns the contract status with the user\'s permissions on it (resource, fields, actions). Requires contract-statuses.view.',
    ],
    'PUT /contract-statuses/{contractStatus}' => [
        'summary' => 'Update a contract status',
        'description' => 'Updates the submitted fields of a contract status. System statuses accept only name and color changes (422 otherwise). Setting is_default true makes it the only default; the default status cannot be deactivated or un-defaulted directly (assign another default instead) and a default must be active, each refused with 422. Returns the status with the user\'s permissions. Requires contract-statuses.update.',
    ],
    'DELETE /contract-statuses/{contractStatus}' => [
        'summary' => 'Delete a contract status',
        'description' => 'Deletes a custom contract status and answers 204 with no content. 422 for a system status; 409 when contracts still use it. Requires contract-statuses.delete.',
    ],
];
