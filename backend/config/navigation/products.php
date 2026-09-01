<?php

// "Prodotti" domain: the product catalogue plus its own lookup tables
// (categories, attributes, VAT rates) under one collapsible parent
// (user decision 2026-07-17). Same collapsible-group shape as above.
return [
    'key' => 'products-group',
    'label' => 'navigation.products',
    'icon' => 'package',
    'route' => null,
    'permission' => null,
    'children' => [
        [
            'key' => 'products',
            'label' => 'navigation.products',
            'icon' => 'package',
            'route' => '/products',
            'permission' => 'products.view',
        ],
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
    ],
];
