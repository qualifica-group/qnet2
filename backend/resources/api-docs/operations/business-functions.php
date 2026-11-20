<?php

return [
    'POST /business-functions' => [
        'summary' => 'Create a business function',
        'description' => 'Creates an organizational function (department, business unit or service) in the hierarchy. type is business_unit, business_service or null for a plain function; parent_id places it under another function; users, operational_sites and company_ids link members, operational sites and companies. Answers 201 with the function and the permissions block. 422 when a submitted field is not editable by the caller. Requires business-functions.create.',
    ],
    'GET /business-functions/for-select' => [
        'summary' => 'List business functions for a select',
        'description' => 'Returns a searchable, paginated list of functions as id, label and manager, ordered by name (items + pagination). search filters by name; ids[] adds those functions even outside the page; exclude_descendants_of leaves out a function and all its descendants (for a parent picker); inactive functions, including those under an inactive ancestor, are excluded unless include_inactive is true. Requires authentication only.',
    ],
    'GET /business-functions/{businessFunction}' => [
        'summary' => 'Get a business function',
        'description' => 'Returns the function with its type, manager, parent, own active flag and the closest inactive ancestor that makes it inactive, its members, operational sites, companies and custom fields, plus the permissions block of the caller on it. Requires business-functions.view.',
    ],
    'PUT /business-functions/{businessFunction}' => [
        'summary' => 'Update a business function',
        'description' => 'Partially updates the function: only submitted fields change. users, operational_sites and company_ids replace the whole linked set; manager_id null removes the manager. 422 when the new parent is the function itself or one of its descendants, when replacing members would leave an active person in no function, or when a submitted field is not editable by the caller. Requires business-functions.update.',
    ],
    'DELETE /business-functions/{businessFunction}' => [
        'summary' => 'Delete a business function',
        'description' => 'Deletes the function and answers 204 with no content. 409 when it is still in use (child functions, members, product lines of campaigns, employments, opportunities or projects, product categories, purchase requests): the message lists each use with its count. Requires business-functions.delete.',
    ],
];
