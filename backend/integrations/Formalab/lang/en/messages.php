<?php

return [
    'not_configured' => 'Formalab is not configured (URL or credentials missing).',
    'login_failed' => 'Formalab authentication failed.',
    'unreachable' => 'Formalab is not reachable.',
    'http_error' => 'Formalab answered with an error (HTTP :status).',
    'not_eligible' => 'This work order\'s category is not linked to Formalab, or its field map does not bring the course / edition name onto a work order field.',
    'class_not_found' => 'The class does not exist on Formalab.',
    'course_name_missing' => 'Fill in the work order field mapped to the course / edition name: Formalab finds the edition from it.',
    'course_name_unmatched' => 'No Formalab edition has this course name. Check it is written exactly as in Formalab (the catalogue updates every 10 minutes).',
    'course_name_ambiguous' => 'Several Formalab editions have this course name: the one to sync cannot be chosen.',
    'invalid_token' => 'Invalid or missing Formalab token.',
    'sync_queued' => 'Formalab synchronization queued.',
    'course_fields_missing' => 'Mapped fields no longer among the attributes, so not updated: :codes.',
    'category' => [
        'enabled_inherited' => 'The Formalab link is decided on the root category: this category inherits it.',
        'attribute_not_in_category' => 'This field is not a work order attribute of the category.',
        'attribute_incompatible' => 'This field\'s type cannot hold the Formalab value.',
        'key_missing' => 'Map the course / edition name: the work order finds the Formalab edition from that field.',
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
