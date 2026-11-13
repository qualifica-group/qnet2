<?php

return [
    'GET /treasury-overview' => [
        'summary' => 'Get the treasury overview',
        'description' => 'Read-only financial snapshot at today, optionally for one company_id: liquidity of the financial accounts (total, by account type, per account), open receivables and payables with not-due and overdue buckets, net position (liquidity plus receivables minus payables), the expected balance at 30, 60 and 90 days, and what is left to verify (installments with no account, this month\'s balance adjustments, statement rows not yet reconciled per account). Amounts are decimal strings. Requires treasury-movements.view.',
    ],
];
