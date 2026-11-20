<?php

return [
    'POST /invoice-installments/collections' => [
        'summary' => 'Collect several installments of one customer at once',
        'description' => 'Records one collection date (and optional financial account) on up to 100 installments, each with its own collected_amount; all or nothing. All installments must belong to invoices of the same customer and must not be already collected, and no amount may exceed its installment (422, errors keyed by item position). A partial amount closes the installment and moves the residual onto a new installment with the original due date. Commissions accrue accordingly. Returns collected_count and residual_count. Requires invoices.collect.',
    ],
    'GET /invoice-installments/{installment}' => [
        'summary' => 'Get an invoice installment',
        'description' => 'Returns the installment with its due date, amount, payment method, collection, write-off, reconciliation and compensation state, status, residual and overdue days, plus its invoice, customer, work order and sites. Amount and collection fields the user cannot see are omitted. Requires invoice-installments.view.',
    ],
    'PATCH /invoice-installments/{installment}' => [
        'summary' => 'Change the due date or payment method of an installment',
        'description' => 'Updates due_date (not before the invoice document date) and/or payment_method_code (an existing payment method code, or null); at least one is required. Amounts cannot be changed here. 409 when the installment is already collected; 403 when a submitted field is locked for the user. Returns the updated installment. Requires invoice-installments.update.',
    ],
    'PUT /invoice-installments/{installment}/collection' => [
        'summary' => 'Record the collection of an installment',
        'description' => 'Records collected_amount and collected_at (optional financial_account_id). A full amount closes the installment. A lower amount requires residual_mode: spread (residual split over the later open installments), new_installment (a new installment due on residual_due_date) or write_off (residual written off, with write_off_reason rounding or bad_debt; amount may then be 0). Commissions accrue accordingly. 409 when already collected; 422 when reconciled or compensated. Returns the whole invoice. Requires invoices.collect.',
    ],
    'DELETE /invoice-installments/{installment}/collection' => [
        'summary' => 'Clear the collection of an installment',
        'description' => 'Removes the collection (and any write-off) from the installment and, when the residual was redistributed, restores the previous installment plan exactly; accrued commissions are reversed. 409 when a later installment touched by the redistribution has been collected meanwhile (clear that one first); 422 when the installment is reconciled or compensated. Returns the whole invoice. Requires invoices.collect.',
    ],
];
