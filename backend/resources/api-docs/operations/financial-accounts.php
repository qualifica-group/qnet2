<?php

return [
    'POST /financial-accounts' => [
        'summary' => 'Create a financial account',
        'description' => 'Creates a bank account, card or cash account of the company. type (bank_account, card, cash) decides the fields: a bank account requires iban (unique) and account_number and may have an address; a card requires card_type (credit, prepaid), card_circuit (visa, mastercard, amex), card_holder, card_number and card_expiry (MM/YYYY), and a credit card requires linked_account_id, a bank account; cash may have an address. Fields of other types, CVV and PIN are refused (422). Answers 201 with the account and the permissions block. Requires financial-accounts.create.',
    ],
    'GET /financial-accounts/for-select' => [
        'summary' => 'List financial accounts for a select',
        'description' => 'Returns a searchable, paginated list of financial accounts as id, label (name), subtitle (IBAN) and meta.type, ordered by name (items + pagination). search filters by name; type (bank_account, card, cash) and company_id narrow the list; ids[] adds those accounts even outside the page. Requires authentication only.',
    ],
    'GET /financial-accounts/{financialAccount}' => [
        'summary' => 'Get a financial account',
        'description' => 'Returns the account with its type, company, bank or card data (the card number only masked, never in clear), address, linked bank account, opening balance and its date, and the current balance (null without treasury-movements.view), plus the permissions block of the caller on it. Requires financial-accounts.view.',
    ],
    'PUT /financial-accounts/{financialAccount}' => [
        'summary' => 'Update a financial account',
        'description' => 'Partially updates the account with the fields of its own type; the type cannot change (422 when a different type is sent). Leaving card_number out keeps the stored number. A credit card must stay linked to a bank account. Requires financial-accounts.update.',
    ],
    'DELETE /financial-accounts/{financialAccount}' => [
        'summary' => 'Delete a financial account',
        'description' => 'Deletes the account and answers 204 with no content. 409 when it is still in use (cards linked to it, bank reconciliations, invoices, purchase invoices or their installments, treasury movements): the message lists each use with its count. Requires financial-accounts.delete.',
    ],
    'GET /financial-accounts/{financialAccount}/bank-reconciliations' => [
        'summary' => 'List the bank reconciliations of an account',
        'description' => 'Returns every saved bank reconciliation of the account, latest statement date first, each with bank balance, reconciled balance, difference, the adjustment movement it created if any, note and author. Not paginated. Requires treasury-movements.view.',
    ],
    'POST /financial-accounts/{financialAccount}/bank-reconciliations' => [
        'summary' => 'Save a bank reconciliation of an account',
        'description' => 'Records the bank balance at statement_date and computes the reconciled balance (opening balance plus the ticked statement rows up to that date) and the difference. With create_adjustment true and a non-zero difference it also creates an already reconciled adjustment movement of treasury_type_id (required in that case) closing the difference. 422 when that date is already reconciled or precedes the opening date. Answers 201. Requires treasury-movements.reconcile, plus treasury-movements.create for the adjustment.',
    ],
    'GET /financial-accounts/{financialAccount}/bank-reconciliations/preview' => [
        'summary' => 'Preview the reconciled balance of an account at a date',
        'description' => 'Returns, for statement_date (required, Y-m-d), the reconciled balance (opening balance plus the ticked statement rows up to that date) and the number of rows not yet ticked, without saving anything. 422 when the date precedes the opening date of the account. Requires treasury-movements.reconcile.',
    ],
    'GET /financial-accounts/{financialAccount}/card-number' => [
        'summary' => 'Reveal the full card number',
        'description' => 'Returns card_number, the full number of a card account in clear: the only endpoint that exposes it. Every call is recorded in the activity log of the account. 404 when the account is not a card or has no number. Requires financial-accounts.revealCardNumber.',
    ],
    'PATCH /financial-accounts/{financialAccount}/opening-balance' => [
        'summary' => 'Set the opening balance of an account',
        'description' => 'Sets opening_balance and opening_balance_date (Y-m-d), the starting point of the account statement and balances; the change is recorded in the activity log. 422 when a treasury movement of the account is dated before the new opening date. Returns the updated account with the permissions block. Requires financial-accounts.manageOpeningBalance.',
    ],
    'POST /financial-accounts/{financialAccount}/reconciliation-marks' => [
        'summary' => 'Tick or untick statement rows as reconciled',
        'description' => 'Marks the given statement rows of the account as reconciled (reconciled true) or not (false). keys (1 to 500) are statement row keys such as movement:12, collection:34 or payment:56, as returned by the statement endpoint; for a transfer only the side of this account changes. All or nothing: 422 when any key is not a row of this account statement. Returns updated, the number of rows actually changed. Requires treasury-movements.reconcile.',
    ],
    'GET /financial-accounts/{financialAccount}/statement' => [
        'summary' => 'Get the statement of a financial account',
        'description' => 'Returns the account statement between date_from and date_to (default: start of the current month to today): opening data, balance at the start of the period, the rows with inflow, outflow and running balance, totals, closing balance, reconciled balance, unreconciled count and today balance. Optional filters (types: manual_income, manual_expense, transfer, adjustment, collection, payment; registry_id; search; reconciled yes or no) narrow the rows only, never the balances. 422 when the period holds too many rows. Requires treasury-movements.view.',
    ],
    'GET /financial-accounts/{financialAccount}/statement/export' => [
        'summary' => 'Export the statement of a financial account',
        'description' => 'Downloads the account statement as an Excel file (account-statement-{id}.xlsx), with the same period and filters as the statement endpoint. 422 when the period holds too many rows. Requires treasury-movements.export.',
    ],
];
