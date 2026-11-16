<?php

return [
    'GET /chart-of-accounts/export' => [
        'summary' => 'Download the chart of accounts',
        'description' => 'Synchronously downloads the whole managerial chart of accounts (groups, accounts and their cost, revenue and treasury sub-accounts) as a file in the same layout accepted by the import: level, code, parent code, name, description, kind, nature, color, active. The query parameter format is xlsx or csv. Requires chart-accounts.export.',
    ],
    'POST /chart-of-accounts/import' => [
        'summary' => 'Preview or import a chart of accounts file',
        'description' => 'Multipart upload of an xlsx or csv file in the export layout. With dry_run=true (or 1) nothing is written and the response is the preview; with dry_run=false the file is applied all-or-nothing, only if no row has errors. Rows are matched by code: an existing code is updated, a new one is created. The response holds applied (whether anything was written), summary (row count and counts to create and update per groups, accounts, cost and revenue sub-accounts) and errors (row, column, message). Requires chart-accounts.import; cost sub-account rows also need the cost-types permissions.',
    ],
    'GET /chart-of-accounts/tree' => [
        'summary' => 'Get the chart of accounts tree',
        'description' => 'Returns the whole three-level managerial chart in one response: groups (with kind) containing accounts, each with its sub-accounts (cost types, revenue types and treasury types, marked by source), naturally sorted by code. Also returns unplaced_cost_types (cost types not linked to any account) and abilities, the caller\'s create/update/delete/export/import rights on each node type. Requires chart-accounts.view.',
    ],
];
