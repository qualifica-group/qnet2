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
        // "Attiva" (spec 0193): receivables; a group with no route of its own.
        [
            'key' => 'accounting-receivable',
            'label' => 'navigation.accountingReceivable',
            'icon' => 'receipt-euro',
            'route' => null,
            'permission' => null,
            'children' => [
                [
                    'key' => 'proforma-requests',
                    'label' => 'navigation.proformaRequests',
                    'icon' => 'receipt-euro',
                    'route' => '/proforma-requests',
                    'permission' => 'proforma-requests.view',
                ],
                [
                    'key' => 'invoices',
                    'label' => 'navigation.invoices',
                    'icon' => 'file-text',
                    'route' => '/invoices',
                    'permission' => 'invoices.view',
                ],
            ],
        ],
    ],
];
