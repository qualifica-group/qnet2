<?php

return [
    'POST /installment-compensations' => [
        'summary' => 'Compensate a receivable installment against a payable one',
        'description' => 'Offsets an open installment of an issued invoice (invoice_installment_id) against an open purchase invoice installment (purchase_invoice_installment_id) of the same registry and company: the lower amount is recorded as collected and paid on compensated_at, with no financial account, accruing commissions. When amounts differ, residual_mode for the larger side is required: spread (onto its later open installments) or new_installment (with residual_due_date). 422 for proformas, different registry or company, or an installment not open. Requires invoices.collect and purchase-invoices.pay.',
    ],
    'DELETE /installment-compensations/{installmentCompensation}' => [
        'summary' => 'Cancel an installment compensation',
        'description' => 'Cancels a compensation: both installments are untagged, their collection and payment are cleared (restoring the previous installment plans and reversing the accrued commissions) and the compensation is deleted. Answers with data null. Requires invoices.collect and purchase-invoices.pay.',
    ],
];
