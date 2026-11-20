<?php

return [
    'POST /revenue-types' => [
        'summary' => 'Create a revenue sub-account',
        'description' => 'Creates a revenue sub-account of the managerial chart of accounts under chart_account_id, which must be an account (not a group) of a revenue-kind group. code (letters, digits, dots and dashes, max 20) must not be used anywhere else in the chart (groups, accounts, cost, revenue or financial sub-accounts); name (max 150) required; description and is_active optional. Returns 201 with the sub-account and the caller\'s update/delete abilities. Requires revenue-types.create.',
    ],
    'GET /revenue-types/for-select' => [
        'summary' => 'List revenue sub-accounts for a select',
        'description' => 'Revenue sub-accounts selectable on products, product categories and invoice lines: active ones under an active account and group, in natural code order, each with code, name, full path, group and account. search matches code or name; offset and limit paginate; ids adds existing selections even when inactive. Returns items, total and offset. Requires revenue-types.view, invoices.view or products.view.',
    ],
    'GET /revenue-types/{revenueType}' => [
        'summary' => 'Get a revenue sub-account',
        'description' => 'Returns the sub-account (parent account id, code, name, description, active flag, institution-commission default flag) with the caller\'s update/delete abilities. Requires revenue-types.view.',
    ],
    'PUT /revenue-types/{revenueType}' => [
        'summary' => 'Update a revenue sub-account',
        'description' => 'Partial update (PATCH accepted) of chart_account_id, code, name, description and is_active, with the same rules as creation. is_institution_commission_default=true makes it the sub-account proposed for the lines of institution invoices, clearing the flag on any other one. Requires revenue-types.update.',
    ],
    'DELETE /revenue-types/{revenueType}' => [
        'summary' => 'Delete a revenue sub-account',
        'description' => 'Deletes the sub-account; answers 200 with no data. 409 when it is still used by invoice lines, products, product categories or treasury movements, with a message listing the uses and their counts. Requires revenue-types.delete.',
    ],
];
