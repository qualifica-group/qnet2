<?php

return [
    'registered_locked' => 'The invoice is registered: it cannot be edited or deleted.',
    'downgrade_forbidden' => 'You are not allowed to change or remove the number of a registered invoice.',
    'has_payments' => 'The document has recorded payments and cannot be deleted.',
    'duplicate_number' => 'An invoice with the same number already exists for this supplier in the same year.',
    'payments_frozen' => 'This field cannot be changed once payments have been recorded.',
    'supplier_locked' => 'The supplier cannot be changed.',
    'company_locked' => 'The company cannot be changed.',
    'total_not_positive' => 'The document total must be greater than zero.',
    'bank_account_company' => 'The bank account must belong to the document company.',
    'line_over_available' => 'exceeds the invoiceable quantity of the purchase order line (available :available).',
    'order_not_receivable' => 'The purchase order is not in a status that allows receiving the invoiced quantities.',
    'receipt_note' => 'Receipt from invoice no. :number',
    'line_order_unknown' => 'The purchase order line does not exist.',
    'order_party_mismatch' => 'The purchase order must have the same supplier and company as the document.',
    'order_not_invoiceable' => 'The purchase order is not in an invoiceable status.',
];
