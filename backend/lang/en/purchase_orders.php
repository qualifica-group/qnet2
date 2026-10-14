<?php

return [
    'company_locked' => 'The company of a purchase order cannot be changed after creation.',
    'not_editable' => 'This purchase order can no longer be changed.',
    'linked_to_purchase_invoice' => 'This purchase order is linked to a purchase invoice.',
    'not_sendable_status' => 'Only approved or sent purchase orders can be sent to the supplier.',
    'has_receipts' => 'A purchase order with receipts or closed residuals cannot go back to draft or validation, nor be cancelled.',
    'not_receivable' => 'Receipts can only be recorded on approved, sent or partially fulfilled purchase orders.',
    'cancelled' => 'The purchase order is cancelled.',
    'line_not_receivable' => 'The line does not belong to the order or its residual is closed.',
    'quantity_exceeds_residual' => 'The quantity exceeds the residual to receive (:residual).',
    'receipt_below_invoiced' => 'The receipt cannot be cancelled: the received quantity would drop below the invoiced one.',
    'line_already_complete' => 'The line is already complete or its residual is closed.',
    'residual_below_billed' => 'The residual cannot be closed: the billed quantity exceeds the received one.',
    'residual_not_closed' => 'The residual of the line is not closed.',
    'only_service_fields' => 'After approval only notes, EA tags, payment, operational site and links can change: supplier, date, description, site and lines are locked.',
];
