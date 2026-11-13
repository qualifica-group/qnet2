<?php

return [
    'POST /api-clients' => [
        'summary' => 'Create an API integration client',
        'description' => 'Creates an external integration client together with its technical user (a service account with the super-admin role that authors every write made with the key) and issues its API key. The response holds the client and plain_text_key: the key is shown only here and never again, only its last four characters are kept. rate_limit_per_minute empty means the default limit; expires_at must be in the future. 422 when the name is already used. Requires api-clients.create.',
    ],
    'GET /api-clients/docs/openapi' => [
        'summary' => 'Download the OpenAPI document of the API',
        'description' => 'Returns the last generated OpenAPI document of the /api endpoints as raw JSON, not wrapped in the response envelope. Reading never regenerates it. 404 with the generation status in data when no document has been generated yet: call the refresh endpoint and poll the status endpoint. Requires api-clients.view.',
    ],
    'GET /api-clients/docs/postman' => [
        'summary' => 'Download the Postman collection of the API',
        'description' => 'Builds a Postman collection from the last generated OpenAPI document and returns it as a JSON file download (qnet-api.postman_collection.json), not wrapped in the response envelope. 404 with the generation status in data when no document has been generated yet. Requires api-clients.view.',
    ],
    'POST /api-clients/docs/refresh' => [
        'summary' => 'Regenerate the API documentation',
        'description' => 'Starts the regeneration of the OpenAPI document in the background and answers 202 with the current status (status generating, generated_at of the previous document). Poll the status endpoint until status is ready, then download the document. Requires api-clients.update.',
    ],
    'GET /api-clients/docs/status' => [
        'summary' => 'Get the API documentation generation status',
        'description' => 'Returns status (ready: a document exists; generating: a regeneration is running; missing: never generated) and generated_at, the time the current document was generated or null. Requires api-clients.view.',
    ],
    'GET /api-clients/{apiClient}' => [
        'summary' => 'Get an API integration client',
        'description' => 'Returns the client with its configured and effective rate limit per minute, expiry and is_expired flag, active flag, the last four characters of its key, when a key or client token was last used, its technical user and its creator. The key itself is never returned. Requires api-clients.view.',
    ],
    'PUT /api-clients/{apiClient}' => [
        'summary' => 'Update an API integration client',
        'description' => 'Updates name, description, rate_limit_per_minute, expires_at and is_active; every field is optional. Renaming the client also renames its technical user. The key is not changed. 422 when the name is already used by another client or expires_at is not in the future. Requires api-clients.update.',
    ],
    'DELETE /api-clients/{apiClient}' => [
        'summary' => 'Delete an API integration client',
        'description' => 'Deletes the client and revokes every token bound to it (its key and the user tokens obtained through client login). The technical user is kept but deactivated, so the activity history stays attributable. Answers with data null. Requires api-clients.delete.',
    ],
    'POST /api-clients/{apiClient}/rotate-key' => [
        'summary' => 'Rotate the key of an API integration client',
        'description' => 'Revokes the current key of the client and issues a new one; user tokens obtained through client login stay valid. The response holds the client and the new plain_text_key, shown only here. The rotation is recorded in the activity log. Requires api-clients.update.',
    ],
];
