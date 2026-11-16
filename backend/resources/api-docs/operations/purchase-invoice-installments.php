<?php

return [
    'POST /purchase-invoice-installments/payments' => [
        'summary' => 'Pay several supplier installments at once',
        'description' => 'Records, all or nothing, the payment of up to 100 installments of the same supplier with one paid_at date and one optional output financial_account_id (it must belong to each document\'s company). Each item gives installment_id and paid_amount (greater than 0, not above the installment); a partial amount closes the installment and moves the residual to a new installment with the original due date. 422 for mixed suppliers, already paid, reconciled or compensated installments. Returns paid_count and residual_count. Requires purchase-invoices.pay.',
    ],
    'GET /purchase-invoice-installments/{installment}' => [
        'summary' => 'Get a supplier invoice installment',
        'description' => 'Returns the payable installment of a purchase invoice: sequence, due date, amount, payment method, paid amount and date, write-off, reconciliation and compensation state, status, residual, overdue days, output account, the parent document with supplier, company, linked purchase orders and sibling installments, plus field permissions and update/pay abilities. Requires purchase-invoice-installments.view.',
    ],
    'PATCH /purchase-invoice-installments/{installment}' => [
        'summary' => 'Update a supplier invoice installment',
        'description' => 'Changes the due_date (YYYY-MM-DD, not before the document date) and/or the payment_method_code (an existing payment method code) of an unpaid installment; at least one is required. Amounts are not editable here. 409 when the installment already has a payment. Returns the updated installment. Requires purchase-invoice-installments.update and edit rights on the submitted fields.',
    ],
    'PUT /purchase-invoice-installments/{installment}/payment' => [
        'summary' => 'Record the payment of a supplier installment',
        'description' => 'Records paid_amount, paid_at and an optional financial_account_id (of the document\'s company). A partial amount needs residual_mode: spread (residual split over the later open installments), new_installment (with residual_due_date) or write_off (with write_off_reason rounding or bad_debt and optional write_off_note; amount 0 allowed only here). The previous plan is kept so clearing the payment restores it. 409 when already paid; 422 when reconciled, compensated or no later open installment exists. Returns the installment. Requires purchase-invoices.pay.',
    ],
    'DELETE /purchase-invoice-installments/{installment}/payment' => [
        'summary' => 'Clear the payment of a supplier installment',
        'description' => 'Removes the recorded payment and, when the payment had redistributed a residual, restores the previous installment plan (removing a residual installment it created). 409 when a later installment touched by that redistribution has been paid since; 422 when the installment is reconciled or compensated. Returns the installment. Requires purchase-invoices.pay.',
    ],
];
