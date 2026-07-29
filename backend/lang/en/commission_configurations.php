<?php

return [
    'roles' => [
        'commercial' => 'Commercial',
        'reporter' => 'Reporter',
        'supervisor' => 'Supervisor',
        'supplier' => 'Supplier',
    ],
    'scopes' => [
        'product_category' => 'Product category',
        'product' => 'Product',
    ],
    'types' => [
        'fixed_amount' => 'Fixed amount',
        'percentage' => 'Percentage',
    ],
    'statuses' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],
    'invalid_scope' => 'The selected scope does not match the category or product.',
    'referenced_delete' => 'This commission configuration is in use and cannot be deleted.',
    'invalid_recipient' => 'The commission recipient is not compatible with its role.',
    'invalid_quote_line_id' => 'A submitted quote line does not belong to this quote or line type.',
];
