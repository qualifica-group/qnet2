<?php

// "Controllo di gestione" (spec 0268): management control reports. The section
// is dropped automatically when the actor can see none of its children.
return [
    'key' => 'management-control-section',
    'label' => 'navigation.managementControl',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'management-control',
            'label' => 'navigation.incomeStatement',
            'icon' => 'chart-column',
            'route' => '/management-control/income-statement',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-exposure',
            'label' => 'navigation.exposure',
            'icon' => 'calendar-clock',
            'route' => '/management-control/exposure',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-cash-flow',
            'label' => 'navigation.cashFlow',
            'icon' => 'wallet',
            'route' => '/management-control/cash-flow',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-trend',
            'label' => 'navigation.trend',
            'icon' => 'trending-up',
            'route' => '/management-control/trend',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-billing',
            'label' => 'navigation.billing',
            'icon' => 'file-text',
            'route' => '/management-control/billing',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-accounts',
            'label' => 'navigation.accounts',
            'icon' => 'landmark',
            'route' => '/management-control/accounts',
            'permission' => 'management-control.view',
        ],
        [
            'key' => 'management-control-agents',
            'label' => 'navigation.agents',
            'icon' => 'hand-coins',
            'route' => '/management-control/agents',
            'permission' => 'management-control.view',
        ],
    ],
];
