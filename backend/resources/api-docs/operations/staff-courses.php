<?php

return [
    'POST /staff-courses' => [
        'summary' => 'Create a staff course',
        'description' => 'Creates a course of the staff training catalogue: unique name, optional description, duration_minutes, validity_months (1-240; trainings of a course without validity never expire) and is_active. job_role_ids lists the job roles that require the course. Returns 201 with the course, its job roles and the number of trainings, plus the record and field permissions. Requires staff-courses.create.',
    ],
    'GET /staff-courses/for-select' => [
        'summary' => 'List staff courses for a select',
        'description' => 'Returns active staff courses as select options (id and name as label) ordered by name. search matches the name; offset and limit page the list; ids[] adds the given ids, even inactive, to hydrate an existing value. Responds with items and pagination. Needs only authentication.',
    ],
    'GET /staff-courses/{staffCourse}' => [
        'summary' => 'Get a staff course',
        'description' => 'Returns the staff course with the job roles that require it and the number of recorded trainings, plus the record and field permissions. Requires staff-courses.view.',
    ],
    'PUT /staff-courses/{staffCourse}' => [
        'summary' => 'Update a staff course',
        'description' => 'Updates the submitted fields of the staff course (partial update, PATCH accepted). When job_role_ids is sent it replaces the whole set of requiring job roles; when omitted the links are left unchanged. A new validity does not change the expiry already stored on past trainings. Returns the course. Requires staff-courses.update.',
    ],
    'DELETE /staff-courses/{staffCourse}' => [
        'summary' => 'Delete a staff course',
        'description' => 'Deletes the staff course and answers 204. 409 when it is still required by a job role or used by a recorded training: deactivate it with is_active false instead. Requires staff-courses.delete.',
    ],
];
