<?php

return [
    'POST /treasury-types' => [
        'summary' => 'Create a financial sub-account',
        'description' => 'Creates a financial (treasury) sub-account of the managerial chart of accounts, used to classify treasury movements, under chart_account_id, which must be an account (not a group) of a financial-kind group. code (letters, digits, dots and dashes, max 20) must not be used anywhere else in the chart (groups, accounts, cost, revenue or financial sub-accounts), else 422; name (max 150) required; description and is_active optional. Returns 201 with the sub-account and the caller\'s update/delete abilities. Requires treasury-types.create.',
    ],
    'GET /treasury-types/for-select' => [
        'summary' => 'List financial sub-accounts for a select',
        'description' => 'Financial sub-accounts selectable on treasury movements: active ones under an active account and group, in natural code order, each with code, name, full path, group and account. search matches code or name; offset and limit paginate; ids adds existing selections even when inactive. Returns items, total and offset. Requires treasury-types.view or treasury-movements.view.',
    ],
    'GET /treasury-types/{treasuryType}' => [
        'summary' => 'Get a financial sub-account',
        'description' => 'Returns the sub-account (parent account id, code, name, description, active flag) with the caller\'s update/delete abilities. Requires treasury-types.view.',
    ],
    'PUT /treasury-types/{treasuryType}' => [
        'summary' => 'Update a financial sub-account',
        'description' => 'Partial update (PATCH accepted) of chart_account_id, code, name, description and is_active, with the same rules as creation. Returns the sub-account. Requires treasury-types.update.',
    ],
    'DELETE /treasury-types/{treasuryType}' => [
        'summary' => 'Delete a financial sub-account',
        'description' => 'Deletes the sub-account; answers 200 with no data. 409 when a treasury movement references it. Requires treasury-types.delete.',
    ],
];
