<?php

return [
    'GET /work-order-chat/channels' => [
        'summary' => 'List the chat channels of the caller',
        'description' => 'Returns the project work orders where the caller is a supervisor or participant (a super-admin outside the team is not listed), newest last message first: work_order_id, code, title, registry_name, unread_count (other people\'s live messages after the read marker), can_write (false once the work is completed or closed), channel (the realtime channel name) and last_message (author_name, body_preview up to 120 characters, null when deleted, is_deleted, created_at) or null. Empty without project-work-orders.view. Use the work-orders chat endpoints to read and write a channel.',
    ],
];
