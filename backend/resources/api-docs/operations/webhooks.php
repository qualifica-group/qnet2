<?php

return [
    'POST /webhooks/archivio-smart' => [
        'summary' => 'Receive an ArchivioSmart webhook event',
        'description' => 'Server-to-server endpoint, no bearer token: the Signature header must be the hex HMAC-SHA256 of the raw body with the webhook secret saved in the ArchivioSmart connection. Also sent: X-Archiviosmart-Event, X-Archiviosmart-Event-Id (unique, a repeated id is acknowledged and not processed again) and X-Archiviosmart-Timestamp. Answers 202 with success true and processes the event in the background (document.uploaded, document.validated, request.completed, request.expired update the document request; ping and request.created are only recorded; an unknown request is ignored). Answers 401 on a missing or wrong signature and 404 while no webhook secret is configured.',
    ],
];
