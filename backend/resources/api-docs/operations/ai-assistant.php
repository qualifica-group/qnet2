<?php

return [
    'GET /ai-assistant/config' => [
        'summary' => 'Get the AI assistant configuration',
        'description' => 'Returns what a client needs before chatting: whether the floating and page assistant are enabled, whether a provider is configured, the driver and model name, the limits (message length, guide length, mentions, files, file size, accepts_attachments, accepted MIME types) and the modules whose records the user may mention. Provider keys are never exposed. Requires ai-assistant.use.',
    ],
    'GET /ai-assistant/settings' => [
        'summary' => 'Get the AI assistant settings',
        'description' => 'Returns the single settings row: general switch, chosen model, additional instructions and their length limit, monthly spend caps, OpenRouter routing, whether the OpenRouter key is present on the server (never its value) and who changed the settings last and when. The row is created with the initial values on first read. Reserved to the super-admin role (403 otherwise).',
    ],
    'PUT /ai-assistant/settings' => [
        'summary' => 'Save the AI assistant settings',
        'description' => 'Changes the fields sent (enabled, model_id, instructions, spend_cap_person_usd, spend_cap_total_usd, routing) and records author and date in the activity log. A changed model_id must be in the filtered model list, otherwise 422 with "The selected model is not available." (503 when OpenRouter cannot confirm it); the saved model is accepted without asking OpenRouter. Reserved to the super-admin role.',
    ],
    'GET /ai-assistant/models' => [
        'summary' => 'List the models the assistant can use',
        'description' => 'Reads OpenRouter from the backend (cached for a few minutes) and returns only models with tool calling and text-only input and output, with context length and prices in dollars per million tokens (input, output, cache read), the flag of the chosen model, search on id and name, and offset and limit (default 50, max 100). 503 when OpenRouter is not reachable. Reserved to the super-admin role.',
    ],
    'GET /ai-assistant/models/providers' => [
        'summary' => 'List the providers without data retention of a model',
        'description' => 'Takes model_id (author/slug) in the query and returns the providers of that model that keep no prompts and support tools, with availability, prices per million tokens and, when OpenRouter publishes them, speed and latency (null otherwise). 503 when OpenRouter is not reachable. Reserved to the super-admin role.',
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
        'summary' => 'Send a message and start an assistant turn',
        'description' => 'Sends one user turn (content, locale it or en, optional mentions as module + record id, optional files as multipart, refused with 422 on files when the model is text-only, optional page guide text; the model receives only module and id of a mention). The message is saved at once and the turn is queued: answers 202 with the saved user message and the turn (status queued), with no stream; the answer is read by polling the turn. 409 when the conversation already has a turn that is not final; 503 with data.reason not_configured or switched_off, in which case nothing is written. Validation and mention access (403/422) are checked before any row is written. The message stays even if the turn fails. Owner only; requires ai-assistant.use.',
    ],
    'GET /ai-assistant/conversations/{conversation}/turns/{turn}' => [
        'summary' => 'Get the state of an assistant turn',
        'description' => 'Returns the turn the browser polls until its status is final: status (queued, running, awaiting_confirmation, completed, failed, cancelled, limit_reached), the partial answer text while running, the steps taken with a short translated label and their state, the saved answer message once completed, the queue position while queued and, for a turn that ended without an answer, a fixed error code and text. The turn must belong to the conversation (404 otherwise); only the owner can read it (403). Requires ai-assistant.use.',
    ],
    'POST /ai-assistant/conversations/{conversation}/turns/{turn}/confirm' => [
        'summary' => 'Confirm the open preview of an assistant turn',
        'description' => 'Takes preview_id and confirms the preview the turn is waiting on: the confirmation goes through the confirmation route of the connector, never through a tool, and the turn is queued again to tell the result. Answers 202 with the turn. 404 when the preview is not the one of this turn; 409 with data.reason when it cannot be confirmed or is no longer open (decided, cancelled or expired); 503 when the connector cannot be reached, in which case the preview stays open and nothing is repeated. Only the owner of the conversation; requires ai-assistant.use.',
    ],
    'POST /ai-assistant/conversations/{conversation}/turns/{turn}/cancel' => [
        'summary' => 'Cancel the open preview of an assistant turn',
        'description' => 'Takes preview_id and cancels the preview the turn is waiting on, so nothing is written, and the turn is queued again to tell it. Answers 202 with the turn. 404 when the preview is not the one of this turn; 409 with data.reason when it is no longer open. Only the owner of the conversation; requires ai-assistant.use.',
    ],
    'GET /ai-assistant/costs' => [
        'summary' => 'Get the AI assistant costs',
        'description' => 'Takes from and to (dates, to not in the future, from not after to, within the maximum range) and returns the period, the totals, the cost by day, by function, by user and by model (dollars, requests and tokens), and the month spend against the caps. Reserved to the super-admin role (403 otherwise).',
    ],
    'GET /ai-assistant/messages/{message}/attachments/{attachment}' => [
        'summary' => 'Download an AI message attachment',
        'description' => 'Downloads the file attached to a message, as a binary response with its original name. Only the owner of the message\'s conversation can download it (403 otherwise); 404 when the file no longer exists. Requires ai-assistant.use.',
    ],
];
