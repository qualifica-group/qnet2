<?php

return [
    'POST /staff-trainings' => [
        'summary' => 'Record a staff training',
        'description' => 'Records that a person (user_id) attended a staff course (staff_course_id) on completed_on (Y-m-d, not in the future) with outcome passed or failed, and optional notes. The expiry date is computed from the course validity months at save time, and the actor is stored as recorder. Returns 201 with the training. Requires staff-trainings.manage.',
    ],
    'GET /staff-trainings/compliance' => [
        'summary' => 'Get the staff training compliance overview',
        'description' => 'Returns one row per active visible person and course, for the courses required by the person\'s job roles (active courses only) or already passed, with the standing derived from the latest passed training: valid, expiring (within expiring_days, also returned), expired or missing. Filters: status, business_function_id, staff_course_id. Visibility: staff-trainings.viewAll or staff-trainings.manage see everyone, a manager sees self plus org-chart subordinates, staff-trainings.viewAny alone sees self; 403 otherwise.',
    ],
    'GET /staff-trainings/{staffTraining}' => [
        'summary' => 'Get a staff training',
        'description' => 'Returns the training with person, course and its validity, completion and expiry dates, outcome, notes, the certificate summary (id, name, size) and who recorded it. 404 when the actor cannot see the person; 403 when the actor has no access to staff trainings at all.',
    ],
    'PUT /staff-trainings/{staffTraining}' => [
        'summary' => 'Update a staff training',
        'description' => 'Updates the submitted fields of the training (partial update, PATCH accepted, same rules as creation). The expiry date is recomputed only when the course or the completion date change. Returns the training. 404 when the actor cannot see it. Requires staff-trainings.manage.',
    ],
    'DELETE /staff-trainings/{staffTraining}' => [
        'summary' => 'Delete a staff training',
        'description' => 'Deletes the training record and answers 204. 404 when the actor cannot see it. Requires staff-trainings.manage.',
    ],
    'POST /staff-trainings/{staffTraining}/attachment' => [
        'summary' => 'Upload the certificate of a staff training',
        'description' => 'Uploads the certificate as multipart field file (pdf, jpg, jpeg or png, extension matching the content, within the configured size limit) and replaces any previous certificate of the training. Returns the training. 404 when the actor cannot see it. Requires staff-trainings.manage.',
    ],
    'GET /staff-trainings/{staffTraining}/attachment' => [
        'summary' => 'Download the certificate of a staff training',
        'description' => 'Streams the certificate file as a download with its original name. 404 when the training has no certificate or the actor cannot see it.',
    ],
    'DELETE /staff-trainings/{staffTraining}/attachment' => [
        'summary' => 'Remove the certificate of a staff training',
        'description' => 'Deletes the certificate file of the training and answers 204. 404 when the actor cannot see the training. Requires staff-trainings.manage.',
    ],
];
