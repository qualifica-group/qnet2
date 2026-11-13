<?php

return [
    'POST /task-templates' => [
        'summary' => 'Create a task template',
        'description' => 'Creates a reusable template of task rows used to generate the tasks of a work order. items (1 to 500) each need title and due_offset_days (days after the work order start date that become the task due date) and may set estimated_minutes, an active open or pending task_status_id, a work order workflow status, a stage (stage_key matching a stages key) and a parent (key/parent_key, up to 3 levels of sub-items, sub-items carry no stage). stages (up to 50) each have a unique key and a name. The rich text description may hold inline images. Answers 201. 422 when the name is already used. Requires task-templates.create.',
    ],
    'GET /task-templates/for-select' => [
        'summary' => 'List task templates for a select',
        'description' => 'Returns the active task templates as id, label (name), subtitle (description) and meta.items_count, ordered by name, searchable by name and paginated (items + pagination). ids[] adds those templates even when inactive or outside the page. Requires authentication only.',
    ],
    'GET /task-templates/{taskTemplate}' => [
        'summary' => 'Get a task template',
        'description' => 'Returns the template with its description, active flag, stages and ordered rows (with sub-rows), plus the permissions block of the caller on it. Requires task-templates.view.',
    ],
    'PUT /task-templates/{taskTemplate}' => [
        'summary' => 'Update a task template',
        'description' => 'Partially updates the template. items, when sent, is the full set of rows: a row with the id of an existing row of this template is updated, a row without id is created, rows left out are deleted. stages works the same way, and an empty stages array removes every stage. Tasks already generated from the template are not changed. Requires task-templates.update.',
    ],
    'DELETE /task-templates/{taskTemplate}' => [
        'summary' => 'Delete a task template',
        'description' => 'Deletes the template with its rows and their files and answers 204 with no content. 409 when work orders have already been generated from it: deactivate it instead. Requires task-templates.delete.',
    ],
];
