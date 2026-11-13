<?php

return [
    'GET /ai-assistant/config' => [
        'summary' => 'Get the AI assistant configuration',
        'description' => 'Returns what a client needs before chatting: whether the floating and page assistant are enabled, whether a provider is configured, the driver and model name, the limits (message length, guide length, mentions, files, file size, accepted MIME types) and the modules whose records the user may mention. Provider keys are never exposed. Requires ai-assistant.use.',
    ],
    'GET /ai-assistant/conversations' => [
        'summary' => 'List my AI assistant conversations',
        'description' => 'Returns the conversations of the authenticated user only, most recently answered first (never answered ones last), as a paginated list driven by offset and limit (default 30). Requires ai-assistant.use.',
    ],
    'POST /ai-assistant/conversations' => [
        'summary' => 'Start an AI assistant conversation',
        'description' => 'Creates an empty conversation owned by the authenticated user, with no body needed; its title is set from the first message. Returns 201 with the conversation. Requires ai-assistant.use.',
    ],
    'GET /ai-assistant/conversations/{conversation}' => [
        'summary' => 'Get an AI assistant conversation with its messages',
        'description' => 'Returns the conversation with all its messages and their attachments. Only the owner can read it (403 otherwise). Requires ai-assistant.use.',
    ],
    'DELETE /ai-assistant/conversations/{conversation}' => [
        'summary' => 'Delete an AI assistant conversation',
        'description' => 'Deletes the conversation, its messages and their uploaded files. Only the owner can delete it (403 otherwise). Returns success with null data. Requires ai-assistant.use.',
    ],
    'POST /ai-assistant/conversations/{conversation}/messages' => [
        'summary' => 'Send a message and stream the AI answer',
        'description' => 'Sends one user turn (content, locale it or en, optional mentions as module + record id, optional files as multipart, optional page guide text) and answers with a text/event-stream: message.created, then delta events with text chunks, then message.completed with the stored answer and conversation, or error. Validation, mention access (403/422) and 503 when no provider is configured are returned as JSON before the stream opens. A failed turn stores nothing. Owner only; requires ai-assistant.use.',
    ],
    'GET /ai-assistant/messages/{message}/attachments/{attachment}' => [
        'summary' => 'Download an AI message attachment',
        'description' => 'Downloads the file attached to a message, as a binary response with its original name. Only the owner of the message\'s conversation can download it (403 otherwise); 404 when the file no longer exists. Requires ai-assistant.use.',
    ],
];
