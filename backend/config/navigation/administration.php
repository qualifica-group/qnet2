<?php

// "Amministrazione": system-level access control and the dynamic-field
// catalogue (who-can-do-what plus admin-only tooling, kept apart from the
// reference-data configuration above). The section
// is dropped automatically when the actor can see none of its children.
return [
    'key' => 'administration',
    'label' => 'navigation.administration',
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
            'key' => 'roles',
            'label' => 'navigation.roles',
            'icon' => 'shield-check',
            'route' => '/roles',
            'permission' => 'roles.view',
        ],
        [
            // Universal custom fields (spec 0021): the admin catalogue
            // of dynamic fields grafted onto every custom-fieldable
            // module. Moved here from Configurazione (user decision
            // 2026-07-17): it is an admin-only tool, not a taxonomy.
            'key' => 'custom-fields',
            'label' => 'navigation.customFields',
            'icon' => 'puzzle',
            'route' => '/custom-fields',
            'permission' => 'custom-fields.view',
        ],
    ],
];
