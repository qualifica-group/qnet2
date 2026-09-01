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
        'recipient' => 'Recipient',
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
    'recipient_type_not_allowed' => 'This recipient type is not allowed for this role.',
    'recipient_not_selected' => 'The commission recipient must be the person selected on the quote for this role.',
    'role_without_recipient' => 'No recipient is selected on the quote for this role, so it cannot be commissioned.',
    'invalid_quote_line_id' => 'A submitted quote line does not belong to this quote or line type.',
    'recipient_role_changed' => 'The role changed recipient type: provide the new recipient or clear it.',
];
