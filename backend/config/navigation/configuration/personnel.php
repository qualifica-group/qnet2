<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > personnel: the pick-lists of that area (D-2).
return [
    'key' => 'config-personnel',
    'label' => 'navigation.personnel',
    'icon' => 'users',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'business-functions',
            'label' => 'navigation.businessFunctions',
            'icon' => 'briefcase',
            'route' => '/business-functions',
            'permission' => 'business-functions.view',
        ],
        [
            'key' => 'job-roles',
            'label' => 'navigation.jobRoles',
            'icon' => 'id-card',
            'route' => '/job-roles',
            'permission' => 'job-roles.view',
        ],
        [
            'key' => 'job-activities',
            'label' => 'navigation.jobActivities',
            'icon' => 'list-checks',
            'route' => '/job-activities',
            'permission' => 'job-activities.view',
        ],
        [
            'key' => 'contract-types',
            'label' => 'navigation.contractTypes',
            'icon' => 'clipboard-list',
            'route' => '/contract-types',
            'permission' => 'contract-types.view',
        ],
        [
            'key' => 'absence-types',
            'label' => 'navigation.absenceTypes',
            'icon' => 'calendar-cog',
            'route' => '/absence-types',
            'permission' => 'absence-types.view',
        ],
        [
            'key' => 'company-closures',
            'label' => 'navigation.companyClosures',
            'icon' => 'calendar-x',
            'route' => '/company-closures',
            'permission' => 'company-closures.view',
        ],
        [
            'key' => 'staff-courses',
            'label' => 'navigation.staffCourses',
            'icon' => 'library',
            'route' => '/staff-courses',
            'permission' => 'staff-courses.viewAny',
        ],
        [
            'key' => 'skills',
            'label' => 'navigation.skills',
            'icon' => 'list-checks',
            'route' => '/skills',
            'permission' => 'skills.viewAny',
        ],
    ],
];
