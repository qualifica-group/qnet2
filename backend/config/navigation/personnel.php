<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Personale": the people records and their training
// and skills. The HR pick-lists are in Configurazione > Personale.
return [
    'key' => 'personnel',
    'label' => 'navigation.personnel',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'users',
            'label' => 'navigation.users',
            'icon' => 'users',
            'route' => '/users',
            'permission' => 'users.view',
        ],
        [
            'key' => 'org-chart',
            'label' => 'navigation.orgChart',
            'icon' => 'workflow',
            'route' => '/org-chart',
            'permission' => 'org-chart.view',
        ],
        [
            'key' => 'staff-trainings',
            'label' => 'navigation.staffTrainings',
            'icon' => 'graduation-cap',
            'route' => '/staff-trainings',
            'permission' => 'staff-trainings.viewAny',
            'open_to_managers' => true,
        ],
        [
            'key' => 'user-skills',
            'label' => 'navigation.userSkills',
            'icon' => 'sparkles',
            'route' => '/user-skills',
            'permission' => 'user-skills.viewAny',
            'open_to_managers' => true,
        ],
    ],
];
