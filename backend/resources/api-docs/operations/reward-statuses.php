<?php

return [
    'POST /reward-statuses' => [
        'summary' => 'Create a reward status',
        'description' => 'Creates a status that rewards can be in. name must be unique; color and group are required, group being pending (awaiting a decision), closed_won (approved) or closed_lost (denied). The status is placed after the existing custom statuses. Returns 201 with the status and the permissions block. Requires reward-statuses.create.',
    ],
    'GET /reward-statuses/for-select' => [
        'summary' => 'List reward statuses for a select',
        'description' => 'Returns active reward statuses as options (id, name, system key) in display order, searchable by name and paginated with offset/limit (max 100); the response holds items and pagination. ids[] appends saved statuses even when inactive, so a reward keeps showing its current status. Requires authentication only.',
    ],
    'POST /reward-statuses/reorder' => [
        'summary' => 'Reorder the reward statuses',
        'description' => 'Sets the display order of the custom reward statuses: ordered_ids must contain exactly every custom status id, once each, without the system statuses (422 otherwise). Returns every status with its new sort_order and system_key. Requires reward-statuses.update.',
    ],
    'GET /reward-statuses/{rewardStatus}' => [
        'summary' => 'Get a reward status',
        'description' => 'Returns the reward status (name, description, color, group, active flag, order, system key) and the actor\'s permissions block. Requires reward-statuses.view.',
    ],
    'PUT /reward-statuses/{rewardStatus}' => [
        'summary' => 'Update a reward status',
        'description' => 'Partially updates name, description, color, group and is_active. On the three system statuses (pending, approved, denied) only name and color can change (422 otherwise). Returns the updated status and the permissions block. Requires reward-statuses.update.',
    ],
    'DELETE /reward-statuses/{rewardStatus}' => [
        'summary' => 'Delete a reward status',
        'description' => 'Deletes the reward status. 422 for a system status; 409 when rewards still use it. Returns 204 no content. Requires reward-statuses.delete.',
    ],
];
