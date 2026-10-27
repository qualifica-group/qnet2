<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > documents: the pick-lists of that area (D-2).
return [
    'key' => 'config-documents',
    'label' => 'navigation.documents',
    'icon' => 'files',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'document-layouts',
            'label' => 'navigation.documentLayouts',
            'icon' => 'files',
            'route' => '/document-layouts',
            'permission' => 'document-layouts.view',
        ],
        [
            'key' => 'email-templates',
            'label' => 'navigation.emailTemplates',
            'icon' => 'mail',
            'route' => '/email-templates',
            'permission' => 'email-templates.view',
        ],
        [
            'key' => 'document-bundles',
            'label' => 'navigation.documentBundles',
            'icon' => 'folder-archive',
            'route' => '/document-bundles',
            'permission' => 'document-bundles.view',
        ],
    ],
];
