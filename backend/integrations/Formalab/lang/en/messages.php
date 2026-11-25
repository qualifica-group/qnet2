<?php

return [
    'not_configured' => 'Formalab is not configured (URL or credentials missing).',
    'login_failed' => 'Formalab authentication failed.',
    'unreachable' => 'Formalab is not reachable.',
    'http_error' => 'Formalab answered with an error (HTTP :status).',
    'not_eligible' => 'This work order\'s category is not linked to Formalab, or its field map does not bring the Formalab class onto a work order field.',
    'class_not_found' => 'The class no longer exists on Formalab: it was removed from the catalogue, the work order link deleted and the chosen class emptied.',
    'course_name_missing' => 'Choose the Formalab class in the work order field mapped to the course / edition name.',
    'invalid_token' => 'Invalid or missing Formalab token.',
    'sync_queued' => 'Formalab synchronization queued.',
    'sync_already_running' => 'A Formalab sync is already running: wait for it to finish.',
    'sync_interrupted' => 'Sync interrupted before finishing (process stopped or timed out): the next one starts over.',
    'learner_not_in_class' => 'The learner is not in this work order\'s class.',
    'no_partners' => 'No commercial partner found in the work order.',
    'course_fields_missing' => 'Mapped fields no longer among the attributes, so not updated: :codes.',
    'category' => [
        'enabled_inherited' => 'The Formalab link is decided on the root category: this category inherits it.',
        'attribute_not_in_category' => 'This field is not a work order attribute of the category.',
        'attribute_incompatible' => 'This field\'s type cannot hold the Formalab value (the course / edition name needs a relation field onto the Formalab classes).',
        'key_missing' => 'Map the course / edition name: it is the Formalab class select the work order picks its edition with.',
    ],
    'health' => [
        'not_probed' => 'Connection not tested: configuration incomplete.',
        'never_synced' => 'The class catalogue has never been synchronized.',
        'last_sync_failed' => 'The last synchronization failed.',
        'sync_stale' => 'The last synchronization is older than expected.',
        'recent_failures' => 'Failed Formalab calls in the last 24 hours.',
        'course_fields_missing' => 'Some fields mapped in the categories no longer exist among the attributes (code renamed or deleted) and are not updated.',
    ],
];
