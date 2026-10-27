<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > system: the pick-lists of that area (D-2).
return [
    'key' => 'config-system',
    'label' => 'navigation.system',
    'icon' => 'shield-check',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'roles',
            'label' => 'navigation.roles',
            'icon' => 'shield-check',
            'route' => '/roles',
            'permission' => 'roles.view',
        ],
        [
            'key' => 'custom-fields',
            'label' => 'navigation.customFields',
            'icon' => 'puzzle',
            'route' => '/custom-fields',
            'permission' => 'custom-fields.view',
        ],
    ],
];
