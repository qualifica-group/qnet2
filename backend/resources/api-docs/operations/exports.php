<?php

return [
    'POST /exports/{domain}' => [
        'summary' => 'Start an export of a table',
        'description' => 'Starts an asynchronous CSV or XLSX export of the table {domain} (same domains as /tables/{domain}) with the grid state, so the file holds the rows the grid shows: format, columns (colId plus the header to print), optional sortModel, filterModel, advancedFilters, customFilterRules, search, and the scope keys opportunityId, quoteId, registryId, referentId, work_order_type honoured by some domains. Answers 201 with export_run in status processing: poll GET /exports/{domain}/{exportRun}, then download. 404 for an unknown domain; requires the domain\'s export permission.',
    ],
    'GET /exports/{domain}/{exportRun}' => [
        'summary' => 'Get the status of an export',
        'description' => 'Returns the caller\'s export run: status (processing, completed or failed), format, file name, row count and has_file, true once the file can be downloaded. 404 when the run belongs to another user or to another domain. Requires the domain\'s export permission.',
    ],
    'GET /exports/{domain}/{exportRun}/download' => [
        'summary' => 'Download an exported file',
        'description' => 'Streams the generated CSV or XLSX file of the caller\'s export run as an attachment. 404 when the run has no completed file yet, or belongs to another user or domain. Requires the domain\'s export permission.',
    ],
];
