<?php

declare(strict_types=1);

namespace App\Tables\InvoiceTags;

use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue for the `invoice-tags` domain (spec
 * 0247, D-5). Extracted out of InvoiceTagsTableDefinition (file-size split,
 * engineering.md §6): pure data, no logic. Every column is a real DB column
 * handled entirely by the generic engine.
 *
 * `color` is deliberately neither sortable nor filterable: it
 * is a swatch, not a meaningful ordering/filter axis
 * (mirrors ContractStatusColumnCatalog's own choice for `color`).
 * `description` is filterable but not sortable (free text).
 */
final class InvoiceTagColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            [
                'id' => 'name',
                'label' => 'invoiceTags.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'invoiceTags.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'color',
                'label' => 'invoiceTags.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'invoiceTags.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'is_active',
                'label' => 'invoiceTags.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'invoiceTags.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ], [
            'name' => [],
            'description' => EditableColumns::NULLABLE,
            'color' => EditableColumns::COLOR,
            'is_active' => [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'sort_order', 'type' => 'number'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'created_at', 'type' => 'date'],
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
                'permission' => 'invoice-tags.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'invoice-tags.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'invoice-tags.viewActivity',
            ],
        ];
    }
}
