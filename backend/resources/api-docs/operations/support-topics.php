<?php

return [
    'POST /support-topics' => [
        'summary' => 'Create a support topic',
        'description' => 'Creates a support request topic (problem type): the template a user\'s IT support request is turned into a task from. name (unique), due_days (days to the due date) and assignee_ids (at least one active user) are required; optional task type, category, priority, importance, an urgent priority and urgent_due_days used for urgent requests, watcher_ids, icon, is_private and requires_closure_feedback. A user cannot be both assignee and watcher (422). Returns 201 with the topic and the permissions block. Requires support-topics.create.',
    ],
    'GET /support-topics/{supportTopic}' => [
        'summary' => 'Get a support topic',
        'description' => 'Returns the support topic with its task type, category, priority, urgent priority, importance, due days, flags, assignees and watchers, plus the actor\'s permissions block. Requires support-topics.view.',
    ],
    'PUT /support-topics/{supportTopic}' => [
        'summary' => 'Update a support topic',
        'description' => 'Partially updates the support topic; assignee_ids and watcher_ids, when sent, replace the current users, and the resulting sets must not overlap (422). Tasks already created from the topic are not changed. Returns the updated topic and the permissions block. Requires support-topics.update.',
    ],
    'DELETE /support-topics/{supportTopic}' => [
        'summary' => 'Delete a support topic',
        'description' => 'Deletes the support topic; tasks already created from it are kept unchanged. Returns 200 with no data. Requires support-topics.delete.',
    ],
];
