<?php

return [
    'POST /pipeline-statuses' => [
        'summary' => 'Create a project status',
        'description' => 'Creates a custom status used by projects and campaigns, with name, optional color and group (open, pending or closed). It is placed after the last custom status, before the closing system status. Answers 201 with the status and the caller\'s permissions. Requires pipeline-statuses.create.',
    ],
    'GET /pipeline-statuses/for-select' => [
        'summary' => 'List project statuses for a select',
        'description' => 'Returns a page of project statuses as options (id, label = name, meta.system_key), in display order. Optional search filters by name; offset and limit (max 100) paginate; ids[] appends those statuses even when outside the page or search, for prefilling an edit form. Response holds items and pagination. Needs only authentication.',
    ],
    'POST /pipeline-statuses/reorder' => [
        'summary' => 'Reorder the project statuses',
        'description' => 'Sets the display order of the custom project statuses: ordered_ids must list every custom status id exactly once, in the new order, and no system status. The system status new stays first and closed stays last. Returns the full list as id, sort_order and system_key. 422 when the set is incomplete, contains a system status or duplicates. Requires pipeline-statuses.update.',
    ],
    'GET /pipeline-statuses/{pipelineStatus}' => [
        'summary' => 'Get a project status',
        'description' => 'Returns the project status with the caller\'s resource, field and action permissions on it. Requires pipeline-statuses.view.',
    ],
    'PUT /pipeline-statuses/{pipelineStatus}' => [
        'summary' => 'Update a project status',
        'description' => 'Partially updates name, color and group of a project status. 422 when anything other than name or color is sent for a system status. Requires pipeline-statuses.update.',
    ],
    'DELETE /pipeline-statuses/{pipelineStatus}' => [
        'summary' => 'Delete a project status',
        'description' => 'Deletes a custom project status. Answers 204 with no content; 422 for a system status; 409 when projects or campaigns still use it, with the counts in the message. Requires pipeline-statuses.delete.',
    ],
];
