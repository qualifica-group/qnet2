<?php

return [
    'POST /work-order-workflows' => [
        'summary' => 'Create a work order workflow',
        'description' => 'Creates a workflow: the working statuses of the work orders whose revenue lines match its criteria (others use the global default set). name (max 191) and criteria (at least one; each a distinct field from the criterion-fields list with an existing value_id) are required; 422 when another workflow has the same criteria combination. statuses adds custom statuses (name, group open/in_progress/completed/closed, optional description, #RRGGBB color, progress_percentage 0-100) between the fixed open, completed and closed rows (customisable via system_key). Returns 201 with the workflow. Requires work-order-workflows.create.',
    ],
    'GET /work-order-workflows/criterion-fields' => [
        'summary' => 'List the fields a workflow can match on',
        'description' => 'Returns the allowed criterion fields (business_function_id, product_category_id, product_category_branch_id), each with its label key, the for-select resource that provides its values and whether it is multi-valued; they are evaluated on the revenue lines programmed in the work order, the branch field matching a category or any of its ancestors. Requires work-order-workflows.view.',
    ],
    'GET /work-order-workflows/default-statuses' => [
        'summary' => 'List the default work order statuses',
        'description' => 'Returns, in order, the global default status set used by work orders that match no workflow: each status with name, description, color, group (open, in_progress, completed, closed), system_key for the fixed rows, progress percentage and sort order. Requires work-order-workflows.view.',
    ],
    'PUT /work-order-workflows/default-statuses' => [
        'summary' => 'Update the default work order statuses',
        'description' => 'Synchronises the global default status set with statuses (required): a row with id updates that status, a row without id creates a custom one, and custom statuses left out are deleted; the fixed open, completed and closed rows accept only descriptive changes and keep their group. 409 when a status to delete is still used by work orders, tasks or task templates; 422 for an unknown id. Returns the updated set. Requires work-order-workflows.update.',
    ],
    'GET /work-order-workflows/{workOrderWorkflow}' => [
        'summary' => 'Get a work order workflow',
        'description' => 'Returns the workflow with its name, active flag, criteria (field, value id and label) and ordered statuses, plus the caller\'s permissions block. Requires work-order-workflows.view.',
    ],
    'PUT /work-order-workflows/{workOrderWorkflow}' => [
        'summary' => 'Update a work order workflow',
        'description' => 'Partial update (PATCH accepted): name and is_active when sent; criteria, when sent, fully replace the existing ones (422 if another workflow has the same combination); statuses, when sent, are synchronised like the default set (id = update, no id = new custom status, omitted custom statuses deleted, 409 if still in use). Returns the workflow with its permissions block. Requires work-order-workflows.update.',
    ],
    'DELETE /work-order-workflows/{workOrderWorkflow}' => [
        'summary' => 'Delete a work order workflow',
        'description' => 'Deletes the workflow with its criteria and statuses; returns 204. 409 when any of its statuses is still used by work orders, tasks or task templates, with the statuses and their counts in the message. Requires work-order-workflows.delete.',
    ],
];
