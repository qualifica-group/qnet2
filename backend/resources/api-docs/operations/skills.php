<?php

return [
    'POST /skills' => [
        'summary' => 'Create a skill',
        'description' => 'Creates a skill of the catalogue with a unique name and optional category, description and is_active. job_roles links it to job roles as a list of {id, min_level}, where min_level is the minimum level the role requires: 1 basic, 2 intermediate, 3 advanced, 4 expert. Answers 201 with the skill, its job roles and users_count, plus the caller\'s permissions. Requires skills.create.',
    ],
    'GET /skills/for-select' => [
        'summary' => 'List active skills for a select',
        'description' => 'Returns a page of active skills as options (id, label = name, meta.category), sorted by name. Optional search filters by name; offset and limit (max 100) paginate; ids[] appends those skills even when inactive or outside the page, for prefilling. Response holds items and pagination. Needs only authentication.',
    ],
    'GET /skills/{skill}' => [
        'summary' => 'Get a skill',
        'description' => 'Returns the skill with the job roles that require it and their minimum level, the number of people holding it (users_count) and the caller\'s permissions. Requires skills.view.',
    ],
    'PUT /skills/{skill}' => [
        'summary' => 'Update a skill',
        'description' => 'Partially updates a skill: only the fields sent change. When job_roles is sent it replaces the whole list of linked roles and minimum levels (an empty list unlinks them all); when omitted the links stay as they are. Requires skills.update.',
    ],
    'DELETE /skills/{skill}' => [
        'summary' => 'Delete a skill',
        'description' => 'Deletes a skill. Answers 204 with no content; 409 when job roles or people still use it, with the counts in the message (deactivate it instead). Requires skills.delete.',
    ],
];
