<?php

return [
    'GET /document-checklist-categories/for-select' => [
        'summary' => 'List document checklist categories for a selector',
        'description' => 'Minimal paginated list of the non-deleted categories (items of { id, label }) ordered by sort_order and name; search filters by name and ids[] adds saved categories back for the edit form. Only requires authentication.',
    ],
    'POST /document-checklist-categories' => [
        'summary' => 'Create a document checklist category',
        'description' => 'Creates a category. name (required, up to 255 characters) must be unique among the non-deleted categories, ignoring upper/lower case; description is optional (up to 2000); sort_order is an integer from 0 to 65535 (default 0). The category starts with sync_status pending and a job sends it to ArchivioSmart after the commit. Answers 201 with the category and a permissions block. Requires document-checklists.create.',
    ],
    'GET /document-checklist-categories/{documentChecklistCategory}' => [
        'summary' => 'Get a document checklist category',
        'description' => 'Returns the category with checklists_count (non-deleted checklists using it), the sync state (sync_status, sync_error, synced_at) and a permissions block for the caller. Deleted categories answer 404. Requires document-checklists.view.',
    ],
    'PUT /document-checklist-categories/{documentChecklistCategory}' => [
        'summary' => 'Update a document checklist category',
        'description' => 'Full replacement of name, description and sort_order. The category goes back to sync_status pending and one sync job is queued; the checklists of the category are not queued again. Requires document-checklists.update.',
    ],
    'DELETE /document-checklist-categories/{documentChecklistCategory}' => [
        'summary' => 'Delete a document checklist category',
        'description' => 'Soft delete: the row is kept (its id is never reused) and a sync job removes the category from ArchivioSmart. A category that still holds non-deleted checklists is refused with 422 on the field category, naming how many; nothing is changed and no job is queued. Answers 204 with no content; a second call answers 404. Requires document-checklists.delete.',
    ],
    'POST /document-checklist-categories/{documentChecklistCategory}/sync' => [
        'summary' => 'Queue the sync of a document checklist category',
        'description' => 'Marks the category pending and queues a sync job, to retry after a failure. Answers 202 with the category. Deleted categories answer 404. Requires document-checklists.update.',
    ],
    'GET /document-checklist-categories/{documentChecklistCategory}/sync-logs' => [
        'summary' => 'List the latest ArchivioSmart calls of a document checklist category',
        'description' => 'Returns the most recent calls (up to 20, newest first) made for this category: operation (upsert or delete), outcome (success, failed or retrying), http_status (null when there was no HTTP response), attempt, message and created_at. Requires document-checklists.view.',
    ],
];
