<?php

return [
    'POST /document-bundles' => [
        'summary' => 'Create a document bundle',
        'description' => 'Creates a document bundle: a named set of files that can be attached in one go to work order emails. name must be unique; is_active controls whether it is offered in selects. Files are uploaded separately through the attachments endpoints, with the bundle as owner. Returns the bundle (files_count included) with a permissions block. Requires document-bundles.create.',
    ],
    'GET /document-bundles/for-select' => [
        'summary' => 'List active document bundles for a select field',
        'description' => 'Lightweight, paginated option list of the active bundles (id, label = name, meta.files_count) ordered by name. search matches the name; offset/limit paginate; ids[] appends those bundles even when outside the page or inactive (to show the current value of a form). Only requires authentication.',
    ],
    'GET /document-bundles/{documentBundle}' => [
        'summary' => 'Get a document bundle',
        'description' => 'Returns the bundle with name, description, active flag and number of files, plus a permissions block for the caller. Requires document-bundles.view.',
    ],
    'PUT /document-bundles/{documentBundle}' => [
        'summary' => 'Update a document bundle',
        'description' => 'Partially updates name (unique), description and is_active; omitted fields are left unchanged. Returns the bundle with a permissions block. Requires document-bundles.update.',
    ],
    'DELETE /document-bundles/{documentBundle}' => [
        'summary' => 'Delete a document bundle',
        'description' => 'Deletes the bundle together with all its files and answers 204 with no content. Requires document-bundles.delete.',
    ],
];
