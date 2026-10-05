<?php

// "Contabilita'": accounting registries (financial accounts: bank accounts,
// cards, cash). The section is dropped automatically when the actor can see
// none of its children.
return [
    'key' => 'accounting',
    'label' => 'navigation.accounting',
    'icon' => null,
    'route' => null,
    'permission' => null,
    'type' => 'section',
    'children' => [
        [
            'key' => 'financial-accounts',
            'label' => 'navigation.financialAccounts',
            'icon' => 'landmark',
            'route' => '/financial-accounts',
            'permission' => 'financial-accounts.view',
        ],
    ],
];
