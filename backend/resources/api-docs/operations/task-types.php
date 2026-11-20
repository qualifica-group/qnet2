<?php

return [
    'POST /task-types' => [
        'summary' => 'Create a task type',
        'description' => 'Creates a task type with a unique name, a badge color and optional description, icon, is_active, is_default and requires_time_entry (default true: completing a task of this type requires a time entry). It is placed last in the display order. Setting is_default clears it on the other types; an inactive type cannot be default (422). Returns 201 with the type and the user\'s permissions. Requires task-types.create.',
    ],
    'GET /task-types/for-select' => [
        'summary' => 'Search task types for a selector',
        'description' => 'Returns a paginated list of active task types in display order, each as id, label (name) and meta color, icon, is_active and is_default. Supports search on the name, offset and limit (default 25, max 100), include_inactive to include deactivated ones, and ids[] to append already selected types. Any authenticated user.',
    ],
    'POST /task-types/reorder' => [
        'summary' => 'Reorder the task types',
        'description' => 'Sets the display order from ordered_ids, which must list every task type, active or not, exactly once (422 otherwise). Returns each type with its new sort_order. Requires task-types.update.',
    ],
    'GET /task-types/{taskType}' => [
        'summary' => 'Get a task type',
        'description' => 'Returns the task type with the user\'s permissions on it (resource, fields, actions). Requires task-types.view.',
    ],
    'PUT /task-types/{taskType}' => [
        'summary' => 'Update a task type',
        'description' => 'Updates the submitted fields (name, description, color, icon, is_active, is_default, requires_time_entry); sort_order cannot be sent. Setting is_default clears it on the other types; a default type must stay active (422). Returns the type with the user\'s permissions. Requires task-types.update.',
    ],
    'DELETE /task-types/{taskType}' => [
        'summary' => 'Delete a task type',
        'description' => 'Deletes the task type and answers 204 with no content. 409 when tasks still use it. Requires task-types.delete.',
    ],
];
