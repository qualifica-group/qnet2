<?php

return [
    'POST /task-priorities' => [
        'summary' => 'Create a task priority',
        'description' => 'Creates a task priority, placed last in the display order. name must be unique; color and icon must be tokens of the application\'s badge palette and icon set. is_default=true makes it the priority used when a task is created without one, and clears the flag on every other priority; 422 when a default is inactive. Returns the priority with a permissions block. Requires task-priorities.create.',
    ],
    'GET /task-priorities/for-select' => [
        'summary' => 'List task priorities for a select field',
        'description' => 'Lightweight, paginated option list in display order: id, label = name and meta with color, icon, active and default flags. Inactive priorities are excluded unless include_inactive=true. search matches the name; offset/limit paginate; ids[] appends those priorities even when outside the page. Only requires authentication.',
    ],
    'POST /task-priorities/reorder' => [
        'summary' => 'Reorder the task priorities',
        'description' => 'Sets the display order: ordered_ids must contain every task priority id exactly once, in the new order (422 when an id is missing, duplicated or unknown). Returns the full list as id and new sort_order. Requires task-priorities.update.',
    ],
    'GET /task-priorities/{taskPriority}' => [
        'summary' => 'Get a task priority',
        'description' => 'Returns the task priority with name, description, color, icon, display order, active and default flags, plus a permissions block for the caller. Requires task-priorities.view.',
    ],
    'PUT /task-priorities/{taskPriority}' => [
        'summary' => 'Update a task priority',
        'description' => 'Partially updates name (unique), description, color, icon, is_active and is_default; sort_order cannot be set here (use reorder). Setting is_default=true clears it on the other priorities; 422 when the default would be inactive. Returns the priority with a permissions block. Requires task-priorities.update.',
    ],
    'DELETE /task-priorities/{taskPriority}' => [
        'summary' => 'Delete a task priority',
        'description' => 'Deletes the task priority and answers 204 with no content. 409, listing the usages, when it is used by tasks or by support topics (as normal or urgent priority). Requires task-priorities.delete.',
    ],
];
