<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > accounting: the pick-lists of that area (D-2).
return [
    'key' => 'config-accounting',
    'label' => 'navigation.accounting',
    'icon' => 'landmark',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'payment-methods',
            'label' => 'navigation.paymentMethods',
            'icon' => 'credit-card',
            'route' => '/payment-methods',
            'permission' => 'payment-methods.view',
        ],
        [
            'key' => 'work-order-payment-statuses',
            'label' => 'navigation.workOrderPaymentStatuses',
            'icon' => 'credit-card',
            'route' => '/work-order-payment-statuses',
            'permission' => 'work-order-payment-statuses.view',
        ],
        [
            'key' => 'invoice-tags',
            'label' => 'navigation.invoiceTags',
            'icon' => 'tag',
            'route' => '/invoice-tags',
            'permission' => 'invoice-tags.view',
        ],
        [
            'key' => 'commission-configurations',
            'label' => 'navigation.commissionConfigurations',
            'icon' => 'percent',
            'route' => '/commission-configurations',
            'permission' => 'commission-configurations.view',
        ],
        [
            'key' => 'enasarco-parameters',
            'label' => 'navigation.enasarcoParameters',
            'icon' => 'percent',
            'route' => '/enasarco-parameters',
            'permission' => 'enasarco-parameters.view',
        ],
    ],
];
