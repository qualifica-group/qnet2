<?php

return [
    'POST /referent-types' => [
        'summary' => 'Create a referent type',
        'description' => 'Creates a type used to classify the referents (contact persons) of a registry; the only field is name. Answers 201 with the type and the permissions block. Requires referent-types.create.',
    ],
    'GET /referent-types/for-select' => [
        'summary' => 'List referent types for a select',
        'description' => 'Returns a searchable, paginated list of referent types as id and label, ordered by name (items + pagination). search filters by name; ids[] adds those types even outside the page. Requires authentication only.',
    ],
    'GET /referent-types/{referentType}' => [
        'summary' => 'Get a referent type',
        'description' => 'Returns the referent type with its custom fields and the permissions block of the caller on it. Requires referent-types.view.',
    ],
    'PUT /referent-types/{referentType}' => [
        'summary' => 'Update a referent type',
        'description' => 'Updates the name (and custom fields) of the referent type; only submitted fields change. 422 when a submitted field is not editable by the caller. Requires referent-types.update.',
    ],
    'DELETE /referent-types/{referentType}' => [
        'summary' => 'Delete a referent type',
        'description' => 'Deletes the referent type and answers 204 with no content. 409 when referents still use it: the message states how many. Requires referent-types.delete.',
    ],
];
