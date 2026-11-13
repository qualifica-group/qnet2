<?php

return [
    'POST /task-statuses' => [
        'summary' => 'Create a task status',
        'description' => 'Creates a task status with a unique name, optional description, a badge color and optional icon from the allowed tokens, completion_percentage (0-100, the task completion it implies), is_active and group, the phase: open, pending, in_validation, closed_positive or closed_negative. It is placed last in the display order; sort_order and system_key cannot be sent. 422 when group is in_validation and completion_percentage is not 100. Answers 201 with the status and the caller\'s permissions. Requires task-statuses.create.',
    ],
    'GET /task-statuses/for-select' => [
        'summary' => 'List task statuses for a select',
        'description' => 'Returns a page of active task statuses in display order, as options with id, label = name and meta holding color, icon, is_active, system_key, group and completion_percentage. Optional search filters by name; include_inactive=true also returns deactivated statuses; offset and limit (max 100) paginate; ids[] appends those statuses even when inactive or outside the page, for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
    'POST /task-statuses/reorder' => [
        'summary' => 'Reorder the task statuses',
        'description' => 'Sets the display order of all task statuses, system ones included: ordered_ids must list every status id exactly once, in the new order. Returns the full list as id, sort_order and system_key. 422 when the set is incomplete, has unknown ids or duplicates. Requires task-statuses.update.',
    ],
    'GET /task-statuses/{taskStatus}' => [
        'summary' => 'Get a task status',
        'description' => 'Returns the task status with the caller\'s resource, field and action permissions on it. Requires task-statuses.view.',
    ],
    'PUT /task-statuses/{taskStatus}' => [
        'summary' => 'Update a task status',
        'description' => 'Partially updates a task status with the same rules as creation, checked against the merged values. A system status accepts only name, color, icon and completion_percentage (422 otherwise); sort_order and system_key are never accepted. Requires task-statuses.update.',
    ],
    'DELETE /task-statuses/{taskStatus}' => [
        'summary' => 'Delete a task status',
        'description' => 'Deletes a task status. Answers 204 with no content; 422 for a system status; 409 when a task or a task template row still uses it. Requires task-statuses.delete.',
    ],
];
