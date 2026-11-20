<?php

/*
 * The Task a proforma request opens for the proforma manager of the offer's
 * company site (spec 0421).
 *
 * `category_name` is matched by name on task_categories (spec 0421, D-3: a
 * documented exception to the taxonomy's no-name-lookup rule); a renamed or
 * missing category leaves the Task without one. `due_days` are calendar days
 * from the request.
 */
return [
    'task' => [
        'category_name' => env('PROFORMA_REQUEST_TASK_CATEGORY', 'Contabile'),
        'due_days' => (int) env('PROFORMA_REQUEST_TASK_DUE_DAYS', 2),
    ],
];
