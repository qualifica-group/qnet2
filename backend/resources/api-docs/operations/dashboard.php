<?php

return [
    'GET /dashboard/tasks' => [
        'summary' => 'Get the task counters of the home dashboard',
        'description' => 'Returns, among the open tasks the caller can see (root tasks and sub-tasks), count and total estimated minutes for: not_completed (all), assigned_to_me, assigned_by_me (with a to_validate counter of those awaiting validation), created_by_me and observed_by_me. Counts use the same filters as the task list, so they match what the list shows. Requires tasks.viewAny.',
    ],
];
