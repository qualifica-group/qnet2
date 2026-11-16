<?php

return [
    'GET /notifications' => [
        'summary' => 'List my notifications',
        'description' => 'Returns the in-app notifications of the authenticated user, newest first, paginated with offset and limit (max 100). filter=unread returns only unread ones (default all). Each item has its uuid id, type, data (title, message, level, action_url, is_cc), read_at (null while unread) and created_at. Response is a list (items + pagination). Only the caller\'s own notifications are ever returned; no permission needed.',
    ],
    'POST /notifications/bulk-read' => [
        'summary' => 'Mark several notifications as read',
        'description' => 'Marks as read the given notifications of the authenticated user: ids is an array of 1 to 500 distinct notification uuids. Ids that are unknown, already read or belong to someone else are silently ignored. Returns marked, the number of notifications actually marked.',
    ],
    'POST /notifications/read-all' => [
        'summary' => 'Mark all my notifications as read',
        'description' => 'Marks every unread notification of the authenticated user as read. Returns marked, the number of notifications marked.',
    ],
    'GET /notifications/unread-count' => [
        'summary' => 'Get my unread notifications count',
        'description' => 'Returns count, the number of unread notifications of the authenticated user, and latest, the most recent unread notification (null when none). Designed to be polled for a badge.',
    ],
    'PATCH /notifications/{notification}/read' => [
        'summary' => 'Mark a notification as read',
        'description' => 'Marks one notification (by uuid) of the authenticated user as read; calling it on an already read notification changes nothing. Returns the notification. 404 when the id is unknown or belongs to another user.',
    ],
    'PATCH /notifications/{notification}/unread' => [
        'summary' => 'Mark a notification as unread',
        'description' => 'Marks one notification (by uuid) of the authenticated user as unread again; calling it on an unread notification changes nothing. Returns the notification. 404 when the id is unknown or belongs to another user.',
    ],
];
