<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Anagrafiche": customers, contacts, companies and sites.
// Referent types are a pick-list: Configurazione > Commerciale.
return [
    'key' => 'registries-section',
    'label' => 'navigation.registries',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'registries',
            'label' => 'navigation.registries',
            'icon' => 'book-user',
            'route' => '/registries',
            'permission' => 'registries.view',
        ],
        [
            'key' => 'referents',
            'label' => 'navigation.referents',
            'icon' => 'contact-round',
            'route' => '/referents',
            'permission' => 'referents.view',
        ],
        [
            'key' => 'companies',
            'label' => 'navigation.companies',
            'icon' => 'building',
            'route' => '/companies',
            'permission' => 'companies.view',
        ],
        [
            'key' => 'company-sites',
            'label' => 'navigation.companySites',
            'icon' => 'building-2',
            'route' => '/company-sites',
            'permission' => 'company-sites.view',
        ],
        [
            'key' => 'operational-sites',
            'label' => 'navigation.operationalSites',
            'icon' => 'map-pin',
            'route' => '/operational-sites',
            'permission' => 'operational-sites.view',
        ],
    ],
];
