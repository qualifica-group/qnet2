<?php

// Spec 0318 (menu on the legacy QNet macro-areas). "Configurazione": every pick-list and setting, one
// group per area, as in the legacy "Impostazioni" (D-2).
return [
    'key' => 'configuration',
    'label' => 'navigation.configuration',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        require __DIR__.'/configuration/commercial.php',
        require __DIR__.'/configuration/tasks.php',
        require __DIR__.'/configuration/products.php',
        require __DIR__.'/configuration/purchasing.php',
        require __DIR__.'/configuration/accounting.php',
        require __DIR__.'/configuration/personnel.php',
        require __DIR__.'/configuration/documents.php',
        require __DIR__.'/configuration/system.php',
    ],
];
