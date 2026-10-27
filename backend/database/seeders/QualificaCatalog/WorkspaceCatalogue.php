<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's three menu apps (spec 0317, user directive 2026-10-27). Pure
 * data, read by QualificaWorkspaceSeeder. Keys are `config('navigation.items')`
 * keys; their order is irrelevant, the menu keeps its own.
 *
 * Each app holds only the pages of its own job (user directive 2026-10-27):
 * absences and time entries live in HR alone. Only the home page and the
 * tasks, every employee's own work list, are in all three.
 */
final class WorkspaceCatalogue
{
    private const array SHARED_KEYS = ['dashboard', 'tasks'];

    /**
     * @return array<int, array{slug: string, name: string, description: string, icon: string, color: string, sort_order: int, navigation_keys: array<int, string>}>
     */
    public static function apps(): array
    {
        return [
            [
                'slug' => 'contabilita',
                'name' => 'Qualifica Contabilità',
                'description' => 'Fatture, prima nota, acquisti, provvigioni e controllo di gestione',
                'icon' => 'calculator',
                'color' => 'emerald',
                'sort_order' => 10,
                'navigation_keys' => [
                    ...self::SHARED_KEYS,
                    // Receivable and payable, with the registry and companies invoices point at.
                    'registries', 'companies', 'financial-accounts', 'ledger',
                    'proforma-requests', 'invoices', 'invoice-installments', 'invoice-tags',
                    'purchase-invoices', 'purchase-invoice-installments',
                    'payment-methods', 'vat-rates', 'work-orders', 'work-order-payment-statuses',
                    // Purchasing.
                    'purchase-requests', 'purchase-request-lines', 'purchase-orders', 'cost-types',
                    'purchase-request-priorities', 'purchase-order-statuses', 'purchase-request-line-statuses',
                    // Commissions.
                    'agent-fiscal-profiles', 'commission-accruals', 'commission-settlement-runs',
                    'commission-settlements', 'enasarco-parameters', 'commission-configurations',
                    // Management control.
                    'management-control', 'management-control-exposure', 'management-control-cash-flow',
                    'management-control-trend', 'management-control-billing', 'management-control-accounts',
                    'management-control-agents',
                ],
            ],
            [
                'slug' => 'hr',
                'name' => 'Qualifica HR',
                'description' => 'Personale, presenze, assenze, formazione e dotazioni',
                'icon' => 'users',
                'color' => 'violet',
                'sort_order' => 20,
                'navigation_keys' => [
                    ...self::SHARED_KEYS,
                    'users', 'org-chart', 'time-entries', 'absences', 'staff-trainings', 'user-skills', 'stock',
                    'business-functions', 'job-roles', 'job-activities', 'contract-types', 'absence-types',
                    'company-closures', 'staff-courses', 'skills', 'operational-sites',
                ],
            ],
            [
                'slug' => 'sales',
                'name' => 'Qualifica Sales',
                'description' => 'Lead, opportunità, offerte, commesse e anagrafiche clienti',
                'icon' => 'target',
                'color' => 'blue',
                'sort_order' => 30,
                'navigation_keys' => [
                    ...self::SHARED_KEYS,
                    // Pipeline.
                    'projects', 'campaigns', 'leads', 'imports', 'pipeline-statuses',
                    'opportunities', 'quote-workflows', 'quotes', 'work-orders', 'contract-statuses', 'contracts',
                    'request-management', 'field-change-requests', 'enrollee-management', 'request-statistics',
                    // The commercial back office asks for proformas (spec 0299 D-8).
                    'proforma-requests',
                    // Customers, catalogue and referral rewards.
                    'registries', 'referents', 'referent-types', 'company-sites',
                    'products', 'product-categories', 'sectors', 'sources', 'tags',
                    'rewarded-referents', 'reward-types', 'reward-statuses',
                ],
            ],
        ];
    }
}
