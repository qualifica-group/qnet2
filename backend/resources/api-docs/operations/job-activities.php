<?php

return [
    'POST /job-activities' => [
        'summary' => 'Create a job activity',
        'description' => 'Creates a job activity of the HR catalogue with a unique name (max 191 characters) and an optional description (max 1000). Links to job roles are not set here: they are managed from the job role. Returns 201 with the activity. Requires job-activities.create.',
    ],
    'GET /job-activities/for-select' => [
        'summary' => 'List job activities for a select',
        'description' => 'Returns job activities as select options (id and name as label) ordered by name. search matches the name; offset and limit page the list; ids[] adds the given ids to hydrate an existing value. Responds with items and pagination. Needs only authentication.',
    ],
    'GET /job-activities/{jobActivity}' => [
        'summary' => 'Get a job activity',
        'description' => 'Returns the job activity with the job roles it is linked to and whether the actor can update or delete it. Requires job-activities.view.',
    ],
    'PUT /job-activities/{jobActivity}' => [
        'summary' => 'Update a job activity',
        'description' => 'Updates the submitted name (unique) and description of the job activity; PATCH is accepted too. Returns the activity with its job roles. Requires job-activities.update.',
    ],
    'DELETE /job-activities/{jobActivity}' => [
        'summary' => 'Delete a job activity',
        'description' => 'Deletes the job activity and answers 204. 409 when it is still linked to a job role. Requires job-activities.delete.',
    ],
];
