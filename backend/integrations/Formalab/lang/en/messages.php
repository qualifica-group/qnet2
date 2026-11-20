<?php

return [
    'not_configured' => 'Formalab is not configured (URL or credentials missing).',
    'login_failed' => 'Formalab authentication failed.',
    'unreachable' => 'Formalab is not reachable.',
    'http_error' => 'Formalab answered with an error (HTTP :status).',
    'not_eligible' => 'This work order does not have the course fields, so it cannot be linked to a Formalab class.',
    'class_not_found' => 'The class does not exist on Formalab.',
    'not_linked' => 'The work order is not linked to any Formalab edition.',
    'invalid_token' => 'Invalid or missing Formalab token.',
    'sync_queued' => 'Formalab synchronization queued.',
    'course_fields_missing' => 'Course fields no longer among the attributes, so not updated: :codes.',
    'health' => [
        'not_probed' => 'Connection not tested: configuration incomplete.',
        'never_synced' => 'The class catalogue has never been synchronized.',
        'last_sync_failed' => 'The last synchronization failed.',
        'sync_stale' => 'The last synchronization is older than expected.',
        'recent_failures' => 'Failed Formalab calls in the last 24 hours.',
        'course_fields_missing' => 'Some course fields no longer exist among the attributes (code renamed or deleted) and are not updated.',
    ],
];
