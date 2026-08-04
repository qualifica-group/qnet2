<?php

// "Anagrafiche" domain: every registry/contact master-data record under
// one collapsible parent. Replaces the former flat "Gestione" section,
// dissolved into domain groups (user decision 2026-07-17). A route-less
// parent renders collapsible; NavigationService drops it when the actor
// can see none of its children. Ordered from the central registry outward.
return [
    'key' => 'registries-group',
    'label' => 'navigation.registries',
    'icon' => 'book-user',
    'route' => null,
    'permission' => null,
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
            // Referent types (spec 0016): the Referent classification
            // pick-list. Moved here from Configurazione: it belongs to
            // the registry domain it classifies (user decision 2026-07-17).
            'key' => 'referent-types',
            'label' => 'navigation.referentTypes',
            'icon' => 'tags',
            'route' => '/referent-types',
            'permission' => 'referent-types.view',
        ],
        [
            'key' => 'companies',
            'label' => 'navigation.companies',
            'icon' => 'building',
            'route' => '/companies',
            'permission' => 'companies.view',
        ],
        [
            // Company Sites (spec 0020): flexible site
            // anagraphic under a Company.
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
