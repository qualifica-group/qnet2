<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Magazzino e Dotazioni", as in the legacy: products,
// stock and the purchasing flow (RDA, lines, orders).
return [
    'key' => 'warehouse',
    'label' => 'navigation.warehouse',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'products',
            'label' => 'navigation.products',
            'icon' => 'package',
            'route' => '/products',
            'permission' => 'products.view',
        ],
        [
            'key' => 'stock',
            'label' => 'navigation.stock',
            'icon' => 'boxes',
            'route' => '/stock',
            'permission' => 'stock.viewAny',
        ],
        [
            'key' => 'purchasing',
            'label' => 'navigation.purchasing',
            'icon' => 'shopping-cart',
            'route' => null,
            'permission' => null,
            'children' => [
                [
                    'key' => 'purchase-requests',
                    'label' => 'navigation.purchaseRequests',
                    'icon' => 'shopping-cart',
                    'route' => '/purchase-requests',
                    'permission' => 'purchase-requests.view',
                ],
                [
                    'key' => 'purchase-request-lines',
                    'label' => 'navigation.purchaseRequestLines',
                    'icon' => 'list-checks',
                    'route' => '/purchase-request-lines',
                    'permission' => 'purchase-requests.view',
                ],
                [
                    'key' => 'purchase-orders',
                    'label' => 'navigation.purchaseOrders',
                    'icon' => 'file-text',
                    'route' => '/purchase-orders',
                    'permission' => 'purchase-orders.view',
                ],
            ],
        ],
    ],
];
