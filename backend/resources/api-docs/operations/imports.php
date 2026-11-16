<?php

return [
    'GET /imports/{domain}' => [
        'summary' => 'List the import runs of a domain',
        'description' => 'Returns the history of import runs of {domain} (leads or request-management), newest first, by every user: file name, status, row counters (total, valid, warning, error, duplicate, imported, modified) and whether an error report exists. Paginated with page and per_page (default 15). 404 for an unknown domain. Requires {domain}.import.',
    ],
    'POST /imports/{domain}' => [
        'summary' => 'Upload a file and start an import',
        'description' => 'Multipart upload of a csv, txt, xlsx or xls file (field file, size limit from server configuration). Creates an import run in status analyzing and queues the analysis of headers and row count; the run then moves to configuring. Answers 201 with import_run: poll GET /imports/{domain}/{importRun} until the status changes. Requires {domain}.import.',
    ],
    'GET /imports/{domain}/mapping-templates' => [
        'summary' => 'List the saved column mapping templates',
        'description' => 'Returns every mapping template saved for {domain}, shared by the whole team, newest first: name, author, column mapping, value mappings and duplicate strategy, reusable to configure a new run. Requires {domain}.import.',
    ],
    'POST /imports/{domain}/mapping-templates' => [
        'summary' => 'Save the mapping of a run as a template',
        'description' => 'Saves, under a name unique within the domain, the column mapping, reusable value mappings and duplicate strategy already stored on the import run import_run_id, so future files with the same columns can reuse them. 404 when the run does not belong to {domain}; 422 when the run has no column mapping yet. Answers 201 with mapping_template. Requires {domain}.import.',
    ],
    'DELETE /imports/{domain}/mapping-templates/{mappingTemplate}' => [
        'summary' => 'Delete a mapping template',
        'description' => 'Deletes a saved mapping template of {domain}. Only its author may delete it (403 otherwise); 404 when the template belongs to another domain. Returns no data.',
    ],
    'GET /imports/{domain}/template' => [
        'summary' => 'Download the CSV template of an import domain',
        'description' => 'File download: a CSV whose only line is the header with the column ids the domain accepts, in their expected order. Requires {domain}.import.',
    ],
    'GET /imports/{domain}/{importRun}' => [
        'summary' => 'Get an import run and its mapping options',
        'description' => 'Polling endpoint of the import wizard. Returns import_run with status (analyzing, configuring, staging, reviewing, processing, completed, failed), counters, detected file columns, current column_mapping, global_config and dedup_strategy, plus what is needed to configure it: target fields, global fields with defaults, a freshly suggested_mapping, allowed dedup_modes, a matching saved template and progress (processed/total) while working. 404 when the run belongs to another domain. Requires {domain}.import.',
    ],
    'PUT /imports/{domain}/{importRun}/configure' => [
        'summary' => 'Configure an import run and start staging',
        'description' => 'Saves the wizard configuration of a run in configuring status: column_mapping (file column key to target field id), global_config (values applied to every row, e.g. campaign, source, products), dedup_strategy (create_new, update_existing, ignore or manual, where manual lets the user decide per duplicate row) and, for domains that have it, value_mappings. Moves the run to staging and queues the validation of every row; the run then reaches reviewing. 422 in any other status. Requires {domain}.import.',
    ],
    'POST /imports/{domain}/{importRun}/confirm' => [
        'summary' => 'Confirm an import run and write the records',
        'description' => 'Moves a run in reviewing to processing and queues the commit: every row not in error or skipped is written in its own transaction, the run ends completed (or failed) and its author receives a notification. Optional convert_to_opportunity (leads only) also creates an opportunity per lead and requires opportunities.create; when the run is not ready it answers 422 with convert_blockers (campaign without product line, row numbers without operator or operational site). 422 in any other status. Requires {domain}.import.',
    ],
    'GET /imports/{domain}/{importRun}/errors' => [
        'summary' => 'Download the error report of an import run',
        'description' => 'File download: a CSV with every rejected row of the run and its reasons. 404 when the run has no error report (has_error_report false on the run). Requires {domain}.import.',
    ],
    'POST /imports/{domain}/{importRun}/rows' => [
        'summary' => 'List the staged rows of an import run',
        'description' => 'Server-side paged grid of the run\'s staged rows: startRow/endRow window, sortModel and filterModel in AG Grid format, free-text search. Each row carries row number, status (valid, warning, error, duplicate, skipped), the duplicate it matches and its resolution, operator, operational site, campaign, products, values and messages. Allowed only in reviewing, completed or failed (422 otherwise). Requires {domain}.import.',
    ],
    'PATCH /imports/{domain}/{importRun}/rows/assign' => [
        'summary' => 'Assign an operator or products to many staged rows',
        'description' => 'Bulk assignment on a run in reviewing. Target rows: row_ids, or all rows except row_ids when select_all is true. mode single assigns operator_id to all; mode balanced spreads rows among the competent operators of each row\'s operational site (optional operators_by_site and use_current_load). product_ids sets the products of interest; at least an operator or products is required. Returns updated and skipped counts. 422 when not in reviewing or a product is outside a row\'s campaign. Requires {domain}.import.',
    ],
    'PATCH /imports/{domain}/{importRun}/rows/{row}' => [
        'summary' => 'Edit a staged import row',
        'description' => 'Corrects one staged row of a run in reviewing: values (field id to new text), geo (country/state/province/city ids), operator_id, operational_site_id, campaign_id and product_ids; only submitted keys change. The row is re-validated and re-checked for duplicates, and the run counters recomputed. Returns the row and the updated counts. 422 when the run is not in reviewing; 404 when the row belongs to another run. Requires {domain}.import.',
    ],
    'PATCH /imports/{domain}/{importRun}/rows/{row}/resolution' => [
        'summary' => 'Decide how to handle a duplicate import row',
        'description' => 'Sets the resolution of a row flagged as duplicate: skip (do not import), create (import as a new record) or update (update the existing record it matches). Applied when the run is confirmed. Returns the row and the updated counts. 422 when the run is not in reviewing or the row is not a duplicate. Requires {domain}.import.',
    ],
    'GET /imports/{domain}/{importRun}/summary' => [
        'summary' => 'Get the recap of an import run before confirming',
        'description' => 'Returns the recap shown before confirmation: row counters, mapped and extra fields, global configuration, duplicate strategy, warning messages, duplicate resolutions (skip, create, update, unresolved) and, for leads, the readiness to convert to opportunities (rows creatable, rows without operator or operational site). Allowed in reviewing, completed or failed (422 otherwise). Requires {domain}.import.',
    ],
    'POST /imports/{domain}/{importRun}/value-mapping' => [
        'summary' => 'Preview the value mapping of an import run',
        'description' => 'For domains with a value-mapping step (request-management): given the current column_mapping (and optional global_config), returns for each mapped value field the distinct values found in the file and the records each can be matched to. Read-only: the chosen matches are saved with configure. 404 for domains without this step; 409 when the run is not in configuring. Requires {domain}.import.',
    ],
];
