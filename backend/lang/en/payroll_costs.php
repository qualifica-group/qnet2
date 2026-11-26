<?php

return [
    'import' => [
        'errors' => [
            'unreadable_file' => 'The file cannot be read: use an xlsx, csv or txt file.',
            'empty_file' => 'The file is empty: it needs the header and at least one row.',
            'missing_columns' => 'Missing columns in the file: :columns.',
            'too_many_rows' => 'The file exceeds the limit of :max rows.',
            'required' => 'Required value.',
            'invalid_amount' => 'The amount is not a number.',
            'invalid_date' => 'Invalid date: use the format dd/mm/yyyy.',
            'invalid_month' => 'Invalid month.',
            'invalid_year' => 'Invalid year.',
            'other_period' => 'The month differs from the other rows (:period): a file holds a single month.',
            'total_mismatch' => 'The total does not match salary + contributions + insurance.',
            'tax_code_not_found' => 'Tax code not found.',
            'tax_code_many_users' => 'Tax code shared by several users.',
            'company_unmapped' => 'Company not associated.',
            'duplicate' => 'Company and tax code already present in another row of the file.',
        ],
    ],
];
