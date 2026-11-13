<?php

return [
    'POST /tasks' => [
        'summary' => 'Create a task',
        'description' => 'Creates a task; the actor becomes its creator. title, requester_id, end_date and assignee_ids are required; optional links to a registry, referent, lead, opportunity, work order (and its stage) or parent task, type, priority, importance, category, watchers, a recurrence rule, nested subtasks (max 50 in total) and is_completed to create it already closed. The status is derived when not given. Assignees and watchers are notified unless notify_assigned_users is false. Requires tasks.create; a parent task must be editable by the actor.',
    ],
    'POST /tasks/bulk' => [
        'summary' => 'Apply an action to many tasks',
        'description' => 'Applies action to up to 200 task_ids, all or nothing: assign (assignee_ids), priority (task_priority_id), start_date or end_date (date), complete (optional time_entry split by time_entry_shares, closure_feedback, validation_status_id), uncomplete, block, unblock or delete. If any task is not visible or not eligible, nothing changes and a 422 lists them in incompatible_tasks with the reason. Returns the number of affected tasks. Requires tasks.update, tasks.complete, tasks.block or tasks.delete according to the action.',
    ],
    'GET /tasks/for-select' => [
        'summary' => 'List tasks for a select',
        'description' => 'Returns the tasks visible to the actor as options (id, title as label, status name as subtitle), ordered by title, searchable by title and paginated with offset/limit (max 100; include_total=false reports has_more instead of the total). exclude_id leaves out one task, e.g. the task itself when picking its parent; ids[] appends already-saved tasks. Requires authentication only.',
    ],
    'GET /tasks/{task}' => [
        'summary' => 'Get a task',
        'description' => 'Returns the task with status, dates, linked records, assignees, watchers, recurrence, visible subtasks, available actions and the permissions block. Requires tasks.view and that the actor takes part in the task (creator, requester, assignee, watcher) unless tasks.viewAll or tasks.viewSite widens visibility; otherwise 403 with errors.access_contacts listing whom to ask for access.',
    ],
    'PUT /tasks/{task}' => [
        'summary' => 'Update a task',
        'description' => 'Partially updates the task; assignee_ids and watcher_ids replace the current ones and only newly added users are notified (unless notify_new_assigned_users is false); recurrence null cancels the series, an object replaces it. In-validation and positively-closed statuses are reachable only through the complete/approve actions (422). On a blocked, closed or in-validation task, or under a frozen parent, only status and closure feedback can change. Requires tasks.update as creator, requester, assignee or manager.',
    ],
    'DELETE /tasks/{task}' => [
        'summary' => 'Delete a task',
        'description' => 'Deletes the task together with all its subtasks in one transaction. Only an open, unblocked task can be deleted, by its creator, requester, an assignee or a manager (403 otherwise); 422 when a subtask cannot be deleted by the actor, 409 when an ancestor task is blocked, closed or awaiting validation. Returns 204 no content. Requires tasks.delete.',
    ],
    'POST /tasks/{task}/approve' => [
        'summary' => 'Approve a task awaiting validation',
        'description' => 'Closes positively a task that is awaiting validation, stamping today as completion date; a linked work order may advance to the status the task carries. Assignees are told it was approved, requester and watchers that it is closed. 409 when blocked, 422 when not awaiting validation or with open subtasks. Returns the task. Requires tasks.validate as creator, requester or manager.',
    ],
    'POST /tasks/{task}/block' => [
        'summary' => 'Block a task',
        'description' => 'Freezes the task: while blocked, complete and approve answer 409 and an update may change only status and closure feedback; unblock, uncomplete or reject lift the block. 422 when it is already blocked, closed or awaiting validation. A notification is sent. Returns the task. Requires tasks.block as creator, requester or manager.',
    ],
    'POST /tasks/{task}/complete' => [
        'summary' => 'Complete a task',
        'description' => 'Completes the task. If it requires validation and the actor is not its creator, requester or manager, validation_status_id (an active in-validation status) is required and the task goes to validation; otherwise it must be omitted and the task closes positively. time_entry (date, task_type_id, minutes, optional times and notes) is required unless the task type opts out; for_all_assignees logs it for every assignee. 409 when blocked; 422 with open subtasks or when already closed or awaiting validation. Notifications are sent. Requires tasks.complete.',
    ],
    'POST /tasks/{task}/reject' => [
        'summary' => 'Reject a task awaiting validation',
        'description' => 'Sends a task awaiting validation back to the assigned status, clearing completion date and closure feedback and lifting any block. 422 when the task is not awaiting validation. A notification is sent. Returns the task. Requires tasks.validate as creator, requester or manager.',
    ],
    'POST /tasks/{task}/request-update' => [
        'summary' => 'Ask for an update on a task',
        'description' => 'Sends a notification with message (3 to 2000 characters) to the task\'s assignees (watchers in copy), its watchers (observers) or everyone (all), as chosen by target. The task itself is not changed. 422 when the task is closed, awaiting validation or blocked. Returns the task. Requires tasks.requestUpdate as creator, requester or manager.',
    ],
    'POST /tasks/{task}/subtasks/reorder' => [
        'summary' => 'Reorder the subtasks of a task',
        'description' => 'Sets the order of the task\'s direct subtasks: ids must be exactly its direct children, each once (422 otherwise). Returns the reordered subtasks. Requires tasks.update on the parent task.',
    ],
    'GET /tasks/{task}/time-entries' => [
        'summary' => 'List the time entries of a task',
        'description' => 'Returns every user\'s time entries logged on the task, newest first, with total_minutes and can_create (whether the actor may log time on it). Requires visibility of the task and time-entries.viewAny.',
    ],
    'POST /tasks/{task}/time-entries' => [
        'summary' => 'Log time on a task',
        'description' => 'Logs a time entry for the authenticated user on the task: date, task_type_id (an active task type) and minutes (1 to 1440) are required, start_time and end_time (HH:MM) go together, notes optional. Title and record links are taken from the task. Returns 201 with the entry. Requires time-entries.create and being able to complete the task (creator, requester, assignee or manager).',
    ],
    'POST /tasks/{task}/unblock' => [
        'summary' => 'Unblock a task',
        'description' => 'Lifts the block of a blocked task so other actions and edits are allowed again. 422 when the task is not blocked. A notification is sent. Returns the task. Requires tasks.block as creator, requester or manager.',
    ],
    'POST /tasks/{task}/uncomplete' => [
        'summary' => 'Reopen a completed task',
        'description' => 'Reopens a closed or in-validation task onto the in-progress status, clearing completion date and closure feedback and lifting any block. 422 when the task is neither closed nor awaiting validation. A notification is sent. Returns the task. Requires tasks.complete as creator, requester, assignee or manager.',
    ],
];
