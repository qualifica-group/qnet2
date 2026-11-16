<?php

return [
    'GET /absence-balances' => [
        'summary' => 'Get the absence balances of a person for a year',
        'description' => 'Returns, for one person and year (default: current year), one balance row per active absence type that deducts a balance: annual allowance (source allowance, default or none), carried-over, accrued-to-date, used (approved), pending (submitted) and residual minutes, plus the person\'s daily minutes. Without user_id it answers for the caller; another person is visible only to their managers or to holders of absence-requests.manageAll (403 otherwise). Requires absence-requests.viewAny.',
    ],
    'GET /absence-balances/overview' => [
        'summary' => 'List the absence balances of every employee',
        'description' => 'HR overview for a year (default: current year): the balance-deducting absence types and, per active person with an employment profile, their daily minutes and one balance row per type. Filter by business_function_id, operational_site_id or a name search; paginated with page and per_page (max 100), pagination in meta. Requires absence-requests.manageAll.',
    ],
    'GET /absence-balances/overview/export' => [
        'summary' => 'Export the absence balances overview',
        'description' => 'Downloads the HR balances overview for a year as a file, with the same filters as the overview (business_function_id, operational_site_id, search) and no paging. format is required: csv or xlsx. Requires absence-requests.manageAll.',
    ],
];
