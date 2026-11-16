<?php

return [
    'POST /treasury-movements' => [
        'summary' => 'Record a manual treasury movement',
        'description' => 'Records a manual cash movement on a financial account. type: income, expense, transfer (to counterpart_account_id, required, a different account of the same company) or adjustment (direction in or out required; other types refuse direction). amount is positive. Exactly one active sub-account is required: revenue_type_id or treasury_type_id for income, cost_type_id or treasury_type_id for expense, treasury_type_id for transfer and adjustment. registry_id and work_order_id are optional links. 422 when the date precedes the opening date of an involved account. Audited in the activity log. Answers 201. Requires treasury-movements.create.',
    ],
    'GET /treasury-movements/{treasuryMovement}' => [
        'summary' => 'Get a treasury movement',
        'description' => 'Returns the movement with type, direction, date, amount, account and counterpart account, description, sub-account (kind, code, name), registry, work order, reconciled flag, number of attachments and author, plus the permissions block of the caller on it. Requires treasury-movements.view.',
    ],
    'PUT /treasury-movements/{treasuryMovement}' => [
        'summary' => 'Update a treasury movement',
        'description' => 'Partially updates the movement; the same rules as creation are checked on the stored values merged with the submitted ones (a sub-account that became inactive is accepted only if unchanged). 422 when the movement is already reconciled: untick it first. The change is recorded in the activity log. Requires treasury-movements.update.',
    ],
    'DELETE /treasury-movements/{treasuryMovement}' => [
        'summary' => 'Delete a treasury movement',
        'description' => 'Deletes the movement and answers 204 with no content. 422 when the movement is already reconciled: untick it first. Requires treasury-movements.delete.',
    ],
];
