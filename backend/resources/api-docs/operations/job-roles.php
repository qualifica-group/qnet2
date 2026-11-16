<?php

return [
    'POST /job-roles' => [
        'summary' => 'Create a job role',
        'description' => 'Creates a job role (HR catalogue) with a unique name, an optional description and the job activities it covers (job_activity_ids, ids of existing job activities). Returns 201 with the role, its activities, the number of employees holding it and update/delete abilities. Requires job-roles.create.',
    ],
    'GET /job-roles/for-select' => [
        'summary' => 'List job roles for a select',
        'description' => 'Returns job roles as id and name options, ordered by name, filtered by search on the name and paginated with offset/limit (max 100); the response holds items and pagination. Pass ids[] to append saved roles missing from the page (edit-mode hydration) without changing the total. Requires authentication only.',
    ],
    'GET /job-roles/{jobRole}' => [
        'summary' => 'Get a job role',
        'description' => 'Returns the job role with its job activities, the number of employment profiles holding it (users_count) and the actor\'s update/delete abilities. Requires job-roles.view.',
    ],
    'PUT /job-roles/{jobRole}' => [
        'summary' => 'Update a job role',
        'description' => 'Updates name, description and, when job_activity_ids is sent, replaces the set of linked job activities. Returns the updated role with its activities and holder count. Requires job-roles.update.',
    ],
    'DELETE /job-roles/{jobRole}' => [
        'summary' => 'Delete a job role',
        'description' => 'Deletes the job role. Refused with 409, naming the count, while it is still assigned to employment profiles. Returns 204 no content. Requires job-roles.delete.',
    ],
];
