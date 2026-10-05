<?php

namespace App\Tables\FinancialAccounts;

use App\Enums\FinancialAccountType;

/**
 * Declarative column/filter/action catalogue for the `financial-accounts`
 * domain (spec 0189). Pure data (no logic), mirroring UnitOfMeasureColumnCatalog.
 * Card numbers are deliberately NOT a column: neither the grid nor the export
 * can ever carry them. `company` is derived from the `company_id` FK, hidden by
 * default and filterable (handled by FinancialAccountsTableDefinition).
 */
final class FinancialAccountColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'financialAccounts.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'iban',
                'label' => 'financialAccounts.columns.iban',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'type',
                'label' => 'financialAccounts.columns.type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => FinancialAccountType::values(),
            ],
            [
                'id' => 'notes',
                'label' => 'financialAccounts.columns.notes',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'company',
                'label' => 'financialAccounts.columns.company',
                'type' => 'text',
                'visible' => false,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'iban', 'type' => 'text'],
            ['columnId' => 'type', 'type' => 'set', 'options' => FinancialAccountType::values()],
            ['columnId' => 'notes', 'type' => 'text'],
            ['columnId' => 'company', 'type' => 'set'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'financial-accounts.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'financial-accounts.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'financial-accounts.viewActivity',
            ],
        ];
    }
}
