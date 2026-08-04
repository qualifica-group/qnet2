<?php

// "Configurazione": cross-cutting lookup tables not tied to a single
// domain. Product/registry pick-lists moved into their domain groups
// above; custom fields moved to Amministrazione (user decision
// 2026-07-17). What remains are the taxonomies shared across modules.
return [
    'key' => 'configuration',
    'label' => 'navigation.configuration',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'business-functions',
            'label' => 'navigation.businessFunctions',
            'icon' => 'briefcase',
            'route' => '/business-functions',
            'permission' => 'business-functions.view',
        ],
        [
            'key' => 'sectors',
            'label' => 'navigation.sectors',
            'icon' => 'list-tree',
            'route' => '/sectors',
            'permission' => 'sectors.view',
        ],
        [
            'key' => 'tags',
            'label' => 'navigation.tags',
            'icon' => 'tag',
            'route' => '/tags',
            'permission' => 'tags.view',
        ],
        [
            'key' => 'sources',
            'label' => 'navigation.sources',
            'icon' => 'waypoints',
            'route' => '/sources',
            'permission' => 'sources.view',
        ],
        [
            'key' => 'commission-configurations',
            'label' => 'navigation.commissionConfigurations',
            'icon' => 'percent',
            'route' => '/commission-configurations',
            'permission' => 'commission-configurations.view',
        ],
        [
            // Payment methods (spec 0068): a standalone,
            // consumer-agnostic lookup describing the payment
            // modalities selectable across the CRM — no single
            // consuming module yet, so it belongs among the
            // cross-cutting taxonomies rather than a domain group.
            'key' => 'payment-methods',
            'label' => 'navigation.paymentMethods',
            'icon' => 'credit-card',
            'route' => '/payment-methods',
            'permission' => 'payment-methods.view',
        ],
        [
            // Document layouts (spec 0069): reusable, block-based
            // document layout catalogue (first consumer: quotes,
            // module-agnostic by design) — a cross-cutting
            // configuration tool, not tied to a single domain group.
            'key' => 'document-layouts',
            'label' => 'navigation.documentLayouts',
            'icon' => 'files',
            'route' => '/document-layouts',
            'permission' => 'document-layouts.view',
        ],
    ],
];
