<?php

return [
    'POST /document-checklists' => [
        'summary' => 'Create a document checklist',
        'description' => 'Creates a checklist with its ordered sections (document groups: 0 to 20, name unique ignoring case, optional description; section ids must be null) and its ordered items (0 to 200; the array order is the display order, item ids must be null) and an optional category_id (a non-deleted document checklist category, null or missing for none). code is stored uppercase, accepts letters, digits, dash and underscore and must be unique among non-deleted checklists. Each item has name, optional description/validation_prompt/max_age_days, optional section_index (index in the sections array of the same request, null for no section), optional date_field (the extracted date field of the age rule: snake_case, max 64, only allowed with max_age_days), is_required, allowed_file_types (at least one of pdf, jpg, jpeg, png, webp, heic, docx, odt, txt, no duplicates), max_file_size_kb (1 to 20480) and min_files/max_files (1 to 10, max_files not below min_files). The checklist starts with sync_status pending and a job sends it to ArchivioSmart after the commit. Answers 201 with the checklist and a permissions block. Requires document-checklists.create.',
    ],
    'GET /document-checklists/{documentChecklist}' => [
        'summary' => 'Get a document checklist',
        'description' => 'Returns the checklist with its sections ({ id, name, description } in display order), its items in display order (each with section_index and date_field), its category ({ id, name } or null), the sync state (sync_status, sync_error, synced_at), code_locked and a permissions block for the caller. Deleted checklists answer 404. Requires document-checklists.view.',
    ],
    'PUT /document-checklists/{documentChecklist}' => [
        'summary' => 'Update a document checklist',
        'description' => 'Full replacement of the checklist, its sections and its items: sections[].id keeps and renames an existing section (it must belong to this checklist), null adds a new one, sections not sent are deleted and their items fall back to no section; items point to a section with section_index; the sections array order is the display order; send items[].id to keep and update an existing item (it must belong to this checklist), null to add a new one; existing items that are not sent are deleted; the array order becomes the display order. When code_locked is true (the checklist was already synced) the code cannot change; sending the same code is accepted. The checklist goes back to sync_status pending and a sync job is queued. Requires document-checklists.update.',
    ],
    'PATCH /document-checklists/{documentChecklist}/activation' => [
        'summary' => 'Activate or deactivate a document checklist',
        'description' => 'Sets is_active (boolean, required) without touching the items, then marks the checklist pending and queues a sync job so ArchivioSmart receives the new value. Returns the checklist with a permissions block. Requires document-checklists.update.',
    ],
    'DELETE /document-checklists/{documentChecklist}' => [
        'summary' => 'Delete a document checklist',
        'description' => 'Soft delete: the row is kept (its id is never reused) and a sync job removes the checklist from ArchivioSmart. Answers 204 with no content; a second call answers 404. Requires document-checklists.delete.',
    ],
    'POST /document-checklists/sync-all' => [
        'summary' => 'Queue the sync of every document checklist',
        'description' => 'Marks every non-deleted category pending and queues one sync job for each, then does the same for every non-deleted checklist, so categories reach ArchivioSmart first. Answers 202 with data.queued (checklists queued) and data.queued_categories (categories queued). Requires document-checklists.update.',
    ],
    'POST /document-checklists/{documentChecklist}/sync' => [
        'summary' => 'Queue the sync of a document checklist',
        'description' => 'Marks the checklist pending and queues a sync job, to retry after a failure. Answers 202 with the checklist. Deleted checklists answer 404. Requires document-checklists.update.',
    ],
    'GET /document-checklists/{documentChecklist}/sync-logs' => [
        'summary' => 'List the latest ArchivioSmart calls of a document checklist',
        'description' => 'Returns the most recent calls (up to 20, newest first) made for this checklist: operation (upsert or delete), outcome (success, failed or retrying), http_status (null when there was no HTTP response), attempt, message and created_at. Requires document-checklists.view.',
    ],
];
