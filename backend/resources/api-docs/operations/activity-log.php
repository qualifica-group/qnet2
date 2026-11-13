<?php

return [
    'GET /activity-log/{resource}/{id}' => [
        'summary' => 'List the change history of a record',
        'description' => 'Returns the audit timeline of one record, newest first: {resource} is a module key (e.g. users, leads, quotes, registries) and {id} the record id; changes of its related records (contacts, addresses, lines...) are aggregated. Each item carries event, module, causer and the changed fields with old/new values and readable labels; fields the caller may not see are hidden. Cursor pagination: per_page (1-100, default 25), cursor = previous next_cursor, optional event (created or updated). 404 for an unknown resource or record; 403 without {resource}.viewActivity and view permission on the record.',
    ],
];
