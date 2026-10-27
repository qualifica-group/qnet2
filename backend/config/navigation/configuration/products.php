<?php

// Spec 0318 (menu on the legacy QNet macro-areas). Configurazione > products: the pick-lists of that area (D-2).
return [
    'key' => 'config-products',
    'label' => 'navigation.products',
    'icon' => 'package',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'product-categories',
            'label' => 'navigation.productCategories',
            'icon' => 'list-tree',
            'route' => '/product-categories',
            'permission' => 'product-categories.view',
        ],
        [
            'key' => 'attributes',
            'label' => 'navigation.attributes',
            'icon' => 'sliders-horizontal',
            'route' => '/attributes',
            'permission' => 'attributes.view',
        ],
        [
            'key' => 'vat-rates',
            'label' => 'navigation.vatRates',
            'icon' => 'percent',
            'route' => '/vat-rates',
            'permission' => 'vat-rates.view',
        ],
        [
            'key' => 'units-of-measure',
            'label' => 'navigation.unitsOfMeasure',
            'icon' => 'ruler',
            'route' => '/units-of-measure',
            'permission' => 'units-of-measure.view',
        ],
        [
            'key' => 'product-typologies',
            'label' => 'navigation.productTypologies',
            'icon' => 'shapes',
            'route' => '/product-typologies',
            'permission' => 'product-typologies.view',
        ],
    ],
];
