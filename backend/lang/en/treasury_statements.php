<?php

return [
    'statement_too_many_rows' => 'Too many rows: narrow the period.',
    'opening_after_movement' => 'The opening date cannot be later than a manual movement already registered on this account.',
    'columns' => [
        'date' => 'Date',
        'type' => 'Type',
        'description' => 'Description',
        'registry' => 'Registry',
        'document' => 'Document',
        'work_order' => 'Work order',
        'inflow' => 'Inflow',
        'outflow' => 'Outflow',
        'balance' => 'Balance',
    ],
    'types' => [
        'manual_income' => 'Income',
        'manual_expense' => 'Expense',
        'transfer' => 'Transfer',
        'adjustment' => 'Adjustment',
        'collection' => 'Collection',
        'payment' => 'Payment',
    ],
];
