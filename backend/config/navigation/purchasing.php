<?php

// "Acquisti" (spec 0208): purchase requests (RDA) and the line management page.
// Both entries are gated by the same `purchase-requests.view` permission; the
// section is dropped automatically when the actor can see none of its children.
return [
    'key' => 'purchasing',
    'label' => 'navigation.purchasing',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
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
    ],
];
