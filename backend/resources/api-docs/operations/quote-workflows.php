<?php

return [
    'POST /quote-workflows' => [
        'summary' => 'Create a quote workflow',
        'description' => 'Creates a set of statuses that quotes follow when they match its criteria. criteria (at least one) are pairs of field (from the criterion-fields endpoint) and value_id (an id of that field selectable entity); each field once, and the combination must not already belong to another workflow (422). statuses lists the intermediate custom statuses (name, description, color, group: open, pending, validated, closed_won, closed_lost, requires_note); the system statuses open, closed_won and closed_lost are always created and can be named by sending them with system_key. Answers 201. Requires quote-workflows.create.',
    ],
    'GET /quote-workflows/criterion-fields' => [
        'summary' => 'List the fields usable as workflow criteria',
        'description' => 'Returns the fields a quote workflow criterion may use, native first then custom: field, label, source (native or custom), the for-select resource that lists its values, whether a quote may carry several values (multi_valued) and whether the value is taken from the parent opportunity (inherited). Requires quote-workflows.view.',
    ],
    'GET /quote-workflows/default-statuses' => [
        'summary' => 'List the default quote statuses',
        'description' => 'Returns the global default status set, used by quotes that match no workflow, in order: each status with name, description, color, sort order, system_key (open, closed_won, closed_lost, or null for a custom status), group and requires_note. Requires quote-workflows.view.',
    ],
    'PUT /quote-workflows/default-statuses' => [
        'summary' => 'Update the default quote statuses',
        'description' => 'Replaces the custom statuses of the global default set with the statuses array: a row with an id updates that status, a row without id creates a custom status, and custom statuses left out are deleted. System statuses can be renamed or recolored but their group cannot change (422). 409 when a status to delete is still used by quotes: the message lists their codes. Returns the updated set. Requires quote-workflows.update.',
    ],
    'GET /quote-workflows/{quoteWorkflow}' => [
        'summary' => 'Get a quote workflow',
        'description' => 'Returns the workflow with its active flag, its criteria (field, value_id and their labels) and its statuses in order, plus the permissions block of the caller on it. Requires quote-workflows.view.',
    ],
    'PUT /quote-workflows/{quoteWorkflow}' => [
        'summary' => 'Update a quote workflow',
        'description' => 'Partially updates name, is_active, criteria and statuses. criteria, when sent, replaces all criteria (at least one; the combination must stay unique, 422 otherwise). statuses, when sent, syncs the custom statuses (id present updates, absent creates, left out deletes); system statuses accept every field but group. 409 when a status to delete is still used by quotes. Requires quote-workflows.update.',
    ],
    'DELETE /quote-workflows/{quoteWorkflow}' => [
        'summary' => 'Delete a quote workflow',
        'description' => 'Deletes the workflow with its criteria and statuses and answers 204 with no content. 409 when any of its statuses is still used by quotes: the message lists the quote codes to move first. Requires quote-workflows.delete.',
    ],
];
