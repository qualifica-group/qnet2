<?php

return [
    'GET /org-chart/functions' => [
        'summary' => 'Get the org chart by business function',
        'description' => 'Returns functions (every business function with type business_unit or business_service, parent_id for the tree, manager and the active people in it, each with avatar, job roles and is_manager) and unassigned_people (active people without any function). People are sorted by name. Requires org-chart.view.',
    ],
    'POST /org-chart/functions/{businessFunction}/parent' => [
        'summary' => 'Move a business function under another parent',
        'description' => 'Re-parents the function: parent_id is the new parent function, or null to make it a root (the key must be present). 422 when the function would become its own parent or a descendant of itself. The move is written to the activity log. Returns the function id and new parent_id. Requires business-functions.update and edit rights on the function\'s parent field (403 otherwise).',
    ],
    'GET /org-chart/people' => [
        'summary' => 'Get the org chart by reporting line',
        'description' => 'Returns the active people sorted by name, each with avatar, job roles, is_manager, the names of their business functions and manager_ids (the ids of the listed people they report to), to draw the hierarchy of managers. Requires org-chart.view.',
    ],
    'POST /org-chart/people/{user}/function' => [
        'summary' => 'Move a person to another business function',
        'description' => 'Moves the person out of from_function_id (null when the person currently has no function; the key must be present) into to_function_id, which must be an active function different from the source; other memberships are kept. 422 when the person is not in the source function. Logged in the activity log. Returns the user id and business_function_ids after the move. Requires users.update and edit rights on the person\'s business functions (403 otherwise).',
    ],
    'POST /org-chart/people/{user}/manager' => [
        'summary' => 'Change a manager of a person',
        'description' => 'Replaces from_manager_id with to_manager_id among the managers the person reports to, keeping the others; from null adds a manager, to null removes one (both keys must be present). 422 when the source is not a current manager, the target is the person or one of their subordinates, the target is not an active person, or the person has no employment profile. Logged in the activity log. Returns the person with all manager_ids. Requires users.update and edit rights on reporting lines (403 otherwise).',
    ],
];
