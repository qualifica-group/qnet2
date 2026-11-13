<?php

return [
    'POST /assignment/selection-scope' => [
        'summary' => 'Get the assignment scope of a selection of records',
        'description' => 'Read-only helper for assigning an operator to a selection. domain is import_rows (send import_run_id plus select_all or row_ids), leads, quotes or enrollees (send ids). Returns the required product categories, the operational site shared by all records (null when mixed), the campaigns spanned, whether one operator can take the whole selection, and per-site operator groups for balanced distribution with the count of records nobody can take. Requires the read permission of the domain: leads.import, leads.viewAny, request-management.viewAny or enrollee-management.viewAny.',
    ],
];
