<?php

// Spec 0318 (menu on the legacy QNet macro-areas). The Task module alone at the top, with the other
// personal pages; its six pick-lists live in Configurazione > Task (D-2).
return [
    'key' => 'tasks',
    'label' => 'navigation.tasks',
    'icon' => 'list-checks',
    'route' => '/tasks',
    'permission' => 'tasks.view',
];
