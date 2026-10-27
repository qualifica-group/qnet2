<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Lavorazioni", as in the legacy: what is delivered
// once an offer is won.
return [
    'key' => 'operations',
    'label' => 'navigation.operations',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'contracts',
            'label' => 'navigation.contracts',
            'icon' => 'files',
            'route' => '/contracts',
            'permission' => 'contracts.view',
        ],
        [
            'key' => 'work-orders',
            'label' => 'navigation.workOrders',
            'icon' => 'clipboard-list',
            'route' => '/work-orders',
            'permission' => 'work-orders.view',
        ],
    ],
];
