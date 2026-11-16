<?php

return [
    'POST /support-requests' => [
        'summary' => 'Send an IT support request',
        'description' => 'Multipart request that turns the caller\'s IT support request into a task: support_topic_id (an active topic from the topics endpoint), description (plain text, max 5000), optional is_urgent, up to 5 attachments, and page_url / user_agent as context. Type, category, priority, importance, assignees, watchers, privacy and deadline come from the topic and cannot be sent (urgent uses the topic\'s urgent priority and deadline when set). Assignees are notified. 422 when the topic has no active assignee. Returns 201 with task_id and can_view_task. Open to any authenticated user.',
    ],
    'GET /support-requests/topics' => [
        'summary' => 'List the IT support request topics',
        'description' => 'Returns the topics the user can choose when sending a support request: the active topics with at least one active assignee, as id, name, description and icon, in display order then name. Open to any authenticated user.',
    ],
];
