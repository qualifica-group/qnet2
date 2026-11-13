<?php

return [
    'POST /reward-types' => [
        'summary' => 'Create a reward type',
        'description' => 'Creates a reward type of the catalogue used to classify the rewards assigned on opportunities: name (required, unique) and color (required). Returns 201 with the reward type and the caller\'s resource, field and action permissions. Requires reward-types.create.',
    ],
    'GET /reward-types/for-select' => [
        'summary' => 'List reward types for a dropdown',
        'description' => 'Returns reward types as id, label (name) and meta.color, ordered by name, filtered by search (name contains) and paginated with offset and limit; ids[] also returns those types for display of a saved value. Response is a list (items + pagination). Requires authentication only.',
    ],
    'GET /reward-types/{rewardType}' => [
        'summary' => 'Get a reward type',
        'description' => 'Returns the reward type (name, color, timestamps) plus the caller\'s resource, field and action permissions on it. Requires reward-types.view.',
    ],
    'PUT /reward-types/{rewardType}' => [
        'summary' => 'Update a reward type',
        'description' => 'Partially updates the reward type\'s name (unique) and color and returns it with the caller\'s permissions. Requires reward-types.update.',
    ],
    'DELETE /reward-types/{rewardType}' => [
        'summary' => 'Delete a reward type',
        'description' => 'Deletes the reward type. 409 when rewards still use it, with a message naming the uses and their count. Returns 204 no content. Requires reward-types.delete.',
    ],
];
