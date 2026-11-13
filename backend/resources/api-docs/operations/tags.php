<?php

return [
    'POST /tags' => [
        'summary' => 'Create a tag',
        'description' => 'Creates a tag in the tags lookup list with the given name (required, max 191 characters). Returns 201 with the tag and the actor\'s permissions block. Requires tags.create.',
    ],
    'GET /tags/for-select' => [
        'summary' => 'List tags for a select',
        'description' => 'Returns tags as id and label options, ordered by name, searchable by name and paginated with offset/limit (max 100); the response holds items and pagination. ids[] appends already-saved tags outside the current page or search. Requires authentication only.',
    ],
    'GET /tags/{tag}' => [
        'summary' => 'Get a tag',
        'description' => 'Returns the tag (id, name, creation date, custom fields) and the actor\'s permissions block. Requires tags.view.',
    ],
    'PUT /tags/{tag}' => [
        'summary' => 'Update a tag',
        'description' => 'Renames the tag (partial update). Returns the updated tag and the permissions block. Requires tags.update.',
    ],
    'DELETE /tags/{tag}' => [
        'summary' => 'Delete a tag',
        'description' => 'Deletes the tag. Returns 204 no content. Requires tags.delete.',
    ],
];
