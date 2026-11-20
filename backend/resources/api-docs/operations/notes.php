<?php

return [
    'GET /notes' => [
        'summary' => 'List the notes of a record',
        'description' => 'Returns the root notes attached to a record, newest first, each with its replies, author, mentions, linked quote and the caller update/delete abilities. entity_type is one of request-management, enrollee-management, tasks, work-orders, proforma-requests, registries, referents, purchase-requests and entity_id the record id. Paginated by cursor: limit (1 to 50, default 20, root notes) and meta.next_cursor/has_more; meta also holds the total, the quotes of the record and whether people outside it may be mentioned. quote_scope is all, general or a quote id of the record. 404 when the record does not exist, 403 when the caller cannot read it.',
    ],
    'POST /notes' => [
        'summary' => 'Add a note to a record',
        'description' => 'Adds a rich text note (HTML body, sanitized; inline images are stored as attachments) to the record given by entity_type and entity_id. parent_id makes it a reply (replies are one level deep and share the quote of their root); quote_id links a root note to a quote of the record. mentions lists the ids of the mentioned users and must match the mentions in the body; newly mentioned users get a notification, and people outside the record, when allowed, are added to it (meta.enlisted_user_ids). Answers 201. Requires notes.create and read access to the record.',
    ],
    'GET /notes/mentionable-users' => [
        'summary' => 'List the users that can be mentioned in a note',
        'description' => 'Returns the users that can be @mentioned in the notes of the record given by entity_type and entity_id, as id, label (name), subtitle (email), avatar, in_record and has_module_access, searchable and paginated (items + pagination). scope record (default) lists the people who can read the record; scope extended lists every active user, and is 403 unless the caller may mention people outside the record. Requires read access to the record.',
    ],
    'PATCH /notes/{note}' => [
        'summary' => 'Edit a note',
        'description' => 'Replaces the body and the mentions of a note and marks it as edited; the record, parent and quote cannot change. Images no longer in the body are deleted, newly mentioned users are notified and people outside the record may be added to it (meta.enlisted_user_ids). 403 when the caller is not the author or has lost read access to the record.',
    ],
    'DELETE /notes/{note}' => [
        'summary' => 'Delete a note',
        'description' => 'Soft-deletes the note; deleting a root note also hides its replies. Answers with data null. Allowed to the author, or with notes.deleteAny to anyone who can read the record of the note (403 otherwise).',
    ],
];
