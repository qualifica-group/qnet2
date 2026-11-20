<?php

return [
    'POST /sources' => [
        'summary' => 'Create a source',
        'description' => 'Creates a source (the channel a lead, quote or campaign comes from) with the required name. Returns 201 with the source and the user\'s permissions on it. Requires sources.create.',
    ],
    'GET /sources/for-select' => [
        'summary' => 'Search sources for a selector',
        'description' => 'Returns a paginated list of sources ordered by name, each as id and label. Supports search on the name, offset and limit (default 25, max 100) and ids[] to append already selected sources. Any authenticated user.',
    ],
    'GET /sources/{source}' => [
        'summary' => 'Get a source',
        'description' => 'Returns the source with the user\'s permissions on it (resource, fields, actions). Requires sources.view.',
    ],
    'PUT /sources/{source}' => [
        'summary' => 'Update a source',
        'description' => 'Renames the source. Returns the source with the user\'s permissions. Requires sources.update.',
    ],
    'DELETE /sources/{source}' => [
        'summary' => 'Delete a source',
        'description' => 'Deletes the source and answers 204 with no content. 409 when leads, quotes or campaigns still use it. Requires sources.delete.',
    ],
];
