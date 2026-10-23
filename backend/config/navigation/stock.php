<?php

// Dotazioni (spec 0307): top-level HR item after Competenze, gated by the
// module's own entry permission.
return [
    'key' => 'stock',
    'label' => 'navigation.stock',
    'icon' => 'package',
    'route' => '/stock',
    'permission' => 'stock.viewAny',
];
