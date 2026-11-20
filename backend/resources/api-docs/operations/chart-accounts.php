<?php

return [
    'POST /chart-accounts' => [
        'summary' => 'Create a chart of accounts entry',
        'description' => 'Creates a group or an account of the chart of accounts. level is group or account: a group requires kind (cost, revenue or financial) and has no parent; an account requires parent_id, the id of a group, and inherits its kind. code (letters, digits, dot, dash, max 20) must be unused across groups, accounts and cost, revenue or financial sub-accounts. Answers 201 with the entry. Requires chart-accounts.create.',
    ],
    'GET /chart-accounts/{chartAccount}' => [
        'summary' => 'Get a chart of accounts entry',
        'description' => 'Returns the group or account with its level, parent, code, name, description, effective kind (an account takes it from its group), active flag, number of child entries (accounts and cost or revenue sub-accounts) and the update and delete abilities of the caller. Requires chart-accounts.view.',
    ],
    'PUT /chart-accounts/{chartAccount}' => [
        'summary' => 'Update a chart of accounts entry',
        'description' => 'Partially updates code, name, description and is_active; the level cannot change. On a group, kind can change only while it has no accounts (422 otherwise) and parent_id is refused; on an account, parent_id may move it to another group of the same kind and kind is refused. Requires chart-accounts.update.',
    ],
    'DELETE /chart-accounts/{chartAccount}' => [
        'summary' => 'Delete a chart of accounts entry',
        'description' => 'Deletes the group or account and answers with data null. 409 when it still has linked entries (accounts under a group, or cost, revenue or treasury sub-accounts under an account): deactivate it or delete them first. Requires chart-accounts.delete.',
    ],
];
