<?php

return [
    'GET /archivio-smart/settings' => [
        'summary' => 'Get the ArchivioSmart connection',
        'description' => 'Returns base_url, has_token (true when an access token is saved), has_webhook_secret (true when a webhook secret is saved), webhook_url (the absolute URL ArchivioSmart must call) and updated_at. The token and the webhook secret are never returned, not even partially. With no connection saved it answers base_url null, has_token false, has_webhook_secret false and updated_at null. Requires document-checklists.manageConnection.',
    ],
    'PUT /archivio-smart/settings' => [
        'summary' => 'Save the ArchivioSmart connection',
        'description' => 'Creates or updates the single connection. base_url is required, must be a URL of at most 255 characters and must use https (http is accepted only in local and testing environments). token is optional, 10 to 500 characters: when it is missing or empty the saved token is kept, otherwise it replaces it. webhook_secret is optional, 10 to 500 characters: omitted keeps the saved secret, an empty value removes it, otherwise it replaces it. Answers like the GET and never returns the token nor the secret. Requires document-checklists.manageConnection.',
    ],
    'POST /archivio-smart/settings/test' => [
        'summary' => 'Test the ArchivioSmart connection',
        'description' => 'Takes no body: calls GET /me on the saved connection. Answers 200 with data.ok (true only when ArchivioSmart replied 200), data.http_status (null when ArchivioSmart could not be reached) and a data.message that never contains the token or the remote response. Answers 422 when no connection is configured. Requires document-checklists.manageConnection.',
    ],
];
