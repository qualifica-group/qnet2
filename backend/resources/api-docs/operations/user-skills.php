<?php

return [
    'POST /user-skills' => [
        'summary' => 'Assign a skill to a person',
        'description' => 'Records that user_id holds skill_id at a level (1 basic, 2 intermediate, 3 advanced, 4 expert) assessed on assessed_on (not in the future), with optional notes; the caller is stored as assessor. One record per person and skill (422 when it already exists). Level changes are kept in the activity log. Answers 201 with the skill record and can_edit. Requires user-skills.manage.',
    ],
    'GET /user-skills/coverage' => [
        'summary' => 'Get the skill coverage of people against their job roles',
        'description' => 'Returns rows of active person and skill required by their job roles, with the required level (highest minimum among their roles), the level held, the user_skill_id if any, and status: covered, below (held under the required level) or missing. Filters: status, business_function_id, skill_id. Only people the caller may see are included: everyone with user-skills.viewAll or manage, otherwise the caller and their subordinates. 403 without any skill access (user-skills.viewAny or subordinates).',
    ],
    'GET /user-skills/{userSkill}' => [
        'summary' => 'Get a skill of a person',
        'description' => 'Returns the person, the skill with its category, level, assessment date, notes, assessor and can_edit. Visible with user-skills.viewAll or manage for everyone, otherwise only for the caller and their subordinates (404 for others); 403 without any skill access.',
    ],
    'PUT /user-skills/{userSkill}' => [
        'summary' => 'Update a skill of a person',
        'description' => 'Partially updates person, skill, level, assessed_on (not in the future) and notes; the caller becomes the assessor. Level changes are kept in the activity log. Returns the skill record. 422 when the person already has that skill in another record. Requires user-skills.manage; 404 when the record is not visible to the caller.',
    ],
    'DELETE /user-skills/{userSkill}' => [
        'summary' => 'Remove a skill from a person',
        'description' => 'Deletes the skill record and answers 204 with no content. Requires user-skills.manage; 404 when the record is not visible to the caller.',
    ],
];
