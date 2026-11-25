<?php

return [
    'import' => [
        'line_description' => 'Net salary :period',
        'errors' => [
            'unreadable_file' => 'The file cannot be read: use an xlsx, csv or txt file.',
            'empty_file' => 'The file is empty: it needs the header and at least one row.',
            'missing_columns' => 'Missing columns in the file: :columns.',
            'too_many_rows' => 'The file exceeds the limit of :max rows.',
            'required' => 'Required value.',
            'invalid_amount' => 'The amount is not a number.',
            'non_positive_amount' => 'The amount must be greater than zero.',
            'invalid_month' => 'Invalid month.',
            'invalid_year' => 'Invalid year.',
            'other_period' => 'The month differs from the other rows (:period): a file holds a single month.',
            'too_long' => 'The value exceeds :max characters.',
            'tax_code_missing' => 'No "Codice fiscale" ripartition in the row.',
            'tax_code_not_found' => 'Tax code not found.',
            'tax_code_many_users' => 'Tax code shared by several users.',
            'company_unmapped' => 'Company not associated.',
            'duplicate' => 'Company, person and payslip type already present in another row of the file.',
            'already_paid' => 'The payslip of the month already exists and has been paid (even partially): it cannot be updated.',
            'cost_type_missing' => 'The payroll cost type does not exist.',
            'payment_method_missing' => 'The payslip payment method does not exist.',
        ],
        'warnings' => [
            'supplier_many_matches' => 'Several registries share the same tax code: a new supplier registry will be created.',
            'iban_changed' => 'The IBAN in the file differs from the stored one: it will be replaced.',
            'iban_no_profile' => 'The user has no employment profile: the IBAN will not be saved.',
            'no_cost_row' => 'No imported personnel cost for this company, person and month.',
        ],
    ],
];
