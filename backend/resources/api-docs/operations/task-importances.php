<?php

return [
    'POST /task-importances' => [
        'summary' => 'Create a task importance',
        'description' => 'Creates an importance level for tasks: name (required, unique), color (required badge color token: slate, gray, red, orange, amber, yellow, green, emerald, teal, blue, indigo, violet, purple, pink), optional description, icon (a lucide icon name from the allowed set), is_active and is_default. The new row is placed last in the order; sort_order cannot be sent. Setting is_default unsets it on the other importances. 422 when an inactive row is made default. Returns 201 with the importance and the caller\'s permissions. Requires task-importances.create.',
    ],
    'GET /task-importances/for-select' => [
        'summary' => 'List task importances for a dropdown',
        'description' => 'Returns active task importances (all of them with include_inactive=true) as id, label (name) and meta (color, icon, is_active, is_default), in display order then name, filtered by search and paginated with offset and limit; ids[] also returns those rows for display of a saved value. Response is a list (items + pagination). Requires authentication only.',
    ],
    'POST /task-importances/reorder' => [
        'summary' => 'Reorder the task importances',
        'description' => 'Sets the display order of the task importances: ordered_ids must list every task importance id exactly once, in the new order. 422 when an id is missing, duplicated or unknown. Returns the full list as id and sort_order. Requires task-importances.update.',
    ],
    'GET /task-importances/{taskImportance}' => [
        'summary' => 'Get a task importance',
        'description' => 'Returns the task importance (name, description, color, icon, sort_order, is_active, is_default, timestamps) plus the caller\'s resource, field and action permissions on it. Requires task-importances.view.',
    ],
    'PUT /task-importances/{taskImportance}' => [
        'summary' => 'Update a task importance',
        'description' => 'Partially updates name (unique), description, color, icon, is_active and is_default; sort_order is changed only through reorder. Setting is_default unsets it on the other importances. 422 when the result would be an inactive default. Returns the importance with the caller\'s permissions. Requires task-importances.update.',
    ],
    'DELETE /task-importances/{taskImportance}' => [
        'summary' => 'Delete a task importance',
        'description' => 'Deletes the task importance. 409 when tasks or support topics still use it, with a message naming the uses and their counts. Returns 204 no content. Requires task-importances.delete.',
    ],
];
