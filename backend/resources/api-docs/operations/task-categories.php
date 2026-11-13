<?php

return [
    'POST /task-categories' => [
        'summary' => 'Create a task category',
        'description' => 'Creates a category used to classify tasks, optionally nested under parent_id (null = root). name (max 191) must be unique among its siblings; color is required and icon optional, both from the fixed badge palette and icon list; description and is_active optional. sort_order and system_key are refused (422): the category is placed last. Returns 201 with the category and the caller\'s permissions block. Requires task-categories.create.',
    ],
    'GET /task-categories/for-select' => [
        'summary' => 'List task categories for a select',
        'description' => 'Option list of task categories as a depth-first tree (each parent followed by its children, siblings in configured order): id, label = name, meta with color, icon, is_active, parent_id and depth. Only active categories unless include_inactive=true; search matches the name; offset and limit (max 100) paginate; ids adds specific categories to show a form\'s current value. Response is items plus pagination. Needs only authentication.',
    ],
    'POST /task-categories/reorder' => [
        'summary' => 'Reorder the task categories',
        'description' => 'Sets the display order of all task categories: ordered_ids must contain exactly every category id once, inactive ones included (422 if any is missing, unknown or repeated). Returns the id and new sort_order of every category. Requires task-categories.update.',
    ],
    'GET /task-categories/{taskCategory}' => [
        'summary' => 'Get a task category',
        'description' => 'Returns the category with its parent, description, color, icon, sort order and active flag, plus the caller\'s permissions block. Requires task-categories.view.',
    ],
    'PUT /task-categories/{taskCategory}' => [
        'summary' => 'Update a task category',
        'description' => 'Partial update (PATCH accepted) of name, parent_id, description, color, icon and is_active. The name must stay unique among the siblings of the resulting parent; 422 when the category would become its own parent or move under one of its descendants, or on a change to a field the caller may not edit. Returns the category with its permissions block. Requires task-categories.update.',
    ],
    'DELETE /task-categories/{taskCategory}' => [
        'summary' => 'Delete a task category',
        'description' => 'Deletes the category; returns 204. 409 when it still has sub-categories or is used by tasks or support topics, with a message listing the uses and their counts. Requires task-categories.delete.',
    ],
];
