<?php

return [
    'GET /provinces' => [
        'summary' => 'List the provinces of a state',
        'description' => 'Returns all provinces of the required state_id, ordered by name, each with id, name and state id. 422 when state_id is missing or unknown. Any authenticated user.',
    ],
    'GET /provinces/for-select' => [
        'summary' => 'Search provinces for a selector',
        'description' => 'Returns a paginated list of provinces of the configured national country as id, label (province name) and subtitle (its region), filtered by search on the name, paged by offset and limit (default 25, max 100). ids[] appends the given provinces even outside the filter, to show already saved values. Any authenticated user.',
    ],
];
