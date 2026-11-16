<?php

return [
    'POST /sectors' => [
        'summary' => 'Create a sector',
        'description' => 'Creates a business sector used to classify registries, optionally under a parent sector (parent_id). code is an optional short code kept as typed (for example 01, 07a) and must be unique; is_active defaults to true. Answers 201 with the sector and the permissions block. 422 when the code is already used. Requires sectors.create.',
    ],
    'GET /sectors/for-select' => [
        'summary' => 'List sectors for a select',
        'description' => 'Returns a searchable, paginated list of active sectors as id and label ("code - name", or just the name without a code), ordered by name (items + pagination). search matches name or code; sectors that are inactive or under an inactive ancestor are left out, except those requested through ids[], which are always added. Requires authentication only.',
    ],
    'GET /sectors/tree' => [
        'summary' => 'Get the sector tree',
        'description' => 'Returns every sector as a nested tree: the root sectors ordered by name, each with id, code, name, parent_id, its own is_active flag and its children. Inactive sectors are included. Requires sectors.viewAny.',
    ],
    'GET /sectors/{sector}' => [
        'summary' => 'Get a sector',
        'description' => 'Returns the sector with its code, name, parent, its own active flag (an inactive ancestor also deactivates it, which this flag does not reflect) and custom fields, plus the permissions block of the caller on it. Requires sectors.view.',
    ],
    'PUT /sectors/{sector}' => [
        'summary' => 'Update a sector',
        'description' => 'Partially updates code, name, parent_id and is_active; only submitted fields change. Deactivating a sector also hides its whole subtree from the selects. 422 when the new parent is the sector itself or one of its descendants, or the code is already used. Requires sectors.update.',
    ],
    'DELETE /sectors/{sector}' => [
        'summary' => 'Delete a sector',
        'description' => 'Deletes the sector and answers 204 with no content. 409 when it still has child sectors or is linked to registries or purchase orders: the message lists each use with its count. Requires sectors.delete.',
    ],
];
