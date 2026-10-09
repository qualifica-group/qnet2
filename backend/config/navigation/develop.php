<?php

// "Develop": developer-only tooling (data migration, system health, API
// clients and their documentation), kept out of Amministrazione. The
// section is dropped automatically when the actor can see none of its
// children.
return [
    'key' => 'develop',
    'label' => 'navigation.develop',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'migrations',
            // Namespaced i18n key: `migrations` strings live in their own
            // i18next namespace (see frontend i18n/index.ts).
            'label' => 'migrations:nav.label',
            'icon' => 'database-zap',
            'route' => '/dev/migrations',
            'permission' => null,
            'role' => 'super-admin',
        ],
        [
            // System health (spec 0187): live subsystem status + online users.
            'key' => 'system-health',
            'label' => 'navigation.systemHealth',
            'icon' => 'activity',
            'route' => '/dev/system-health',
            'permission' => null,
            'role' => 'super-admin',
        ],
        [
            // API clients (spec 0209).
            'key' => 'api-integrations',
            'label' => 'navigation.apiIntegrations',
            'icon' => 'plug',
            'route' => '/dev/api-clients',
            'permission' => 'api-clients.view',
        ],
        [
            // Integrator API documentation, a page apart from the clients.
            'key' => 'api-docs',
            'label' => 'navigation.apiDocs',
            'icon' => 'book-open',
            'route' => '/dev/api-docs',
            'permission' => 'api-clients.view',
        ],
    ],
];
