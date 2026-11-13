<?php

return [
    'POST /payment-methods' => [
        'summary' => 'Create a payment method',
        'description' => 'Creates a payment method for quotes, invoices and purchase documents. code is a unique, immutable snake_case key; payment_method_code is the fiscal code (e.g. MP05), payment_days the days to due date. Installments: installments_count, days_between_installments (required when more than one installment), end_of_month with end_of_month_extra_days, up to two excluded months (1-12), vat_allocation (split, first, last, vat_first = first installment is VAT only, needs 2+ installments) and payment_condition (TP01 installments, TP02 full, TP03 advance). Requires payment-methods.create.',
    ],
    'GET /payment-methods/for-select' => [
        'summary' => 'List active payment methods for a select field',
        'description' => 'Lightweight, paginated option list of the active payment methods (id, label = name, subtitle = code, meta with payment_days and payment_method_code) in display order. search matches the name; offset/limit paginate; ids[] appends those methods even when outside the page or inactive (to show the current value of a form). Only requires authentication.',
    ],
    'POST /payment-methods/reorder' => [
        'summary' => 'Reorder the payment methods',
        'description' => 'Sets the display order: ordered_ids must contain every payment method id exactly once, in the new order (422 when an id is missing, duplicated or unknown). Returns the full list as id and new sort_order. Requires payment-methods.update.',
    ],
    'GET /payment-methods/{paymentMethod}' => [
        'summary' => 'Get a payment method',
        'description' => 'Returns the payment method with its codes, description, payment instructions, payment days, installment schedule, VAT allocation, payment condition, display order and active flag, plus a permissions block for the caller. Requires payment-methods.view.',
    ],
    'PUT /payment-methods/{paymentMethod}' => [
        'summary' => 'Update a payment method',
        'description' => 'Partially updates the payment method: omitted fields are unchanged and code cannot be changed (422 if sent). Same installment rules as creation, checked against the stored values. Returns the payment method with a permissions block. Requires payment-methods.update.',
    ],
    'DELETE /payment-methods/{paymentMethod}' => [
        'summary' => 'Delete a payment method',
        'description' => 'Deletes the payment method and answers 204 with no content. 409, listing the usages, when it is referenced by quotes, invoices, purchase invoices, purchase orders, proforma requests or agent fiscal profiles. Requires payment-methods.delete.',
    ],
];
