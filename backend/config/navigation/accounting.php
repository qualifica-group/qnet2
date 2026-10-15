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
                // spec 0197: installments of the active invoices.
                [
                    'key' => 'invoice-installments',
                    'label' => 'navigation.invoiceInstallments',
                    'icon' => 'calendar-clock',
                    'route' => '/invoice-installments',
                    'permission' => 'invoice-installments.view',
                ],
                // spec 0247: tags of the active invoices.
                [
                    'key' => 'invoice-tags',
                    'label' => 'navigation.invoiceTags',
                    'icon' => 'tag',
                    'route' => '/invoice-tags',
                    'permission' => 'invoice-tags.view',
                ],
            ],
        ],
        // "Passiva" (spec 0211): payables; a group with no route of its own.
        [
            'key' => 'accounting-payable',
            'label' => 'navigation.accountingPayable',
            'icon' => 'wallet',
            'route' => null,
            'permission' => null,
            'children' => [
                // spec 0250: payable documents (proforma and invoices).
                [
                    'key' => 'purchase-invoices',
                    'label' => 'navigation.purchaseInvoices',
                    'icon' => 'file-text',
                    'route' => '/purchase-invoices',
                    'permission' => 'purchase-invoices.view',
                ],
                [
                    'key' => 'purchase-invoice-installments',
                    'label' => 'navigation.purchaseInvoiceInstallments',
                    'icon' => 'calendar-clock',
                    'route' => '/purchase-invoice-installments',
                    'permission' => 'purchase-invoice-installments.view',
                ],
            ],
        ],
        // "Partitario" (spec 0266): customer/supplier ledger of a registry.
        [
            'key' => 'ledger',
            'label' => 'navigation.ledger',
            'icon' => 'book-open',
            'route' => '/ledger',
            'permission' => 'ledger.view',
        ],
        // "Provvigioni agenti" (spec 0212): agent commissions; a group with no route of its own.
        [
            'key' => 'accounting-commissions',
            'label' => 'navigation.accountingCommissions',
            'icon' => 'hand-coins',
            'route' => null,
            'permission' => null,
            'children' => [
                [
                    'key' => 'agent-fiscal-profiles',
                    'label' => 'navigation.agentFiscalProfiles',
                    'icon' => 'id-card',
                    'route' => '/agent-fiscal-profiles',
                    'permission' => 'agent-fiscal-profiles.view',
                ],
                [
                    'key' => 'commission-accruals',
                    'label' => 'navigation.commissionAccruals',
                    'icon' => 'book-open',
                    'route' => '/commission-accruals',
                    'permission' => 'commission-accruals.view',
                ],
                // spec 0213: liquidazione dei maturati (lanci, estratti, parametri ENASARCO).
                [
                    'key' => 'commission-settlement-runs',
                    'label' => 'navigation.commissionSettlementRuns',
                    'icon' => 'play',
                    'route' => '/commission-settlement-runs',
                    'permission' => 'commission-settlements.view',
                ],
                [
                    'key' => 'commission-settlements',
                    'label' => 'navigation.commissionSettlements',
                    'icon' => 'file-spreadsheet',
                    'route' => '/commission-settlements',
                    'permission' => 'commission-settlements.view',
                ],
                [
                    'key' => 'enasarco-parameters',
                    'label' => 'navigation.enasarcoParameters',
                    'icon' => 'percent',
                    'route' => '/enasarco-parameters',
                    'permission' => 'enasarco-parameters.view',
                ],
            ],
        ],
    ],
];
