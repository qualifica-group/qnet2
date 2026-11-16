<?php

return [
    'POST /auth/client-login' => [
        'summary' => 'Sign a QNet user in through an API client',
        'description' => 'Called with the API client key as Bearer token: checks the email and password of a QNet user (same rules as the app login: active, non-technical user) and returns a Bearer token bound to that client, with its expires_at and the user id, name and email. Subsequent calls with that token act with the user\'s permissions and are rate-limited per client. 403 when the Bearer is not a client key; 422 on wrong credentials. Limited to 6 attempts per minute.',
    ],
    'POST /auth/logout' => [
        'summary' => 'Log out and revoke the current token',
        'description' => 'Deletes the access token used for this request; the token stops working immediately. Other tokens of the same user are not affected. Returns no data.',
    ],
];
