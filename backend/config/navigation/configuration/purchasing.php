<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > purchasing: the pick-lists of that area (D-2).
return [
    'key' => 'config-purchasing',
    'label' => 'navigation.purchasing',
    'icon' => 'shopping-cart',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'cost-types',
            'label' => 'navigation.costTypes',
            'icon' => 'tags',
            'route' => '/cost-types',
            'permission' => 'cost-types.view',
        ],
        [
            'key' => 'purchase-request-priorities',
            'label' => 'navigation.purchaseRequestPriorities',
            'icon' => 'flag',
            'route' => '/purchase-request-priorities',
            'permission' => 'purchase-request-priorities.view',
        ],
        [
            'key' => 'purchase-order-statuses',
            'label' => 'navigation.purchaseOrderStatuses',
            'icon' => 'circle-dot',
            'route' => '/purchase-order-statuses',
            'permission' => 'purchase-order-statuses.view',
        ],
        [
            'key' => 'purchase-request-line-statuses',
            'label' => 'navigation.purchaseRequestLineStatuses',
            'icon' => 'list-checks',
            'route' => '/purchase-request-line-statuses',
            'permission' => 'purchase-request-line-statuses.view',
        ],
    ],
];
