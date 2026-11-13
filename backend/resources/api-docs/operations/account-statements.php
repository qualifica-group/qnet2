<?php

return [
    'GET /account-statements/balances' => [
        'summary' => 'Get the balances of the financial accounts',
        'description' => 'Returns the balance of every financial account (id, name, type, company) at the end of date (YYYY-MM-DD, default today), computed from the opening balance plus movements, collections and payments, and the grand total; amounts are decimal strings. Optional company_id restricts to one company\'s accounts. Requires treasury-movements.view.',
    ],
    'GET /account-statements/unassigned' => [
        'summary' => 'List collections and payments with no financial account',
        'description' => 'Lists collected sales installments (source collection) and paid purchase installments (source payment) since the treasury opening date that have no financial account on the installment nor on its document, so they appear in no account statement. Each row has the installment id, document, registry, date and amount; newest first, with count. Optional company_id filter. Requires treasury-movements.view.',
    ],
];
