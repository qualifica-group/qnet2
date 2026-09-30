<?php

namespace App\Tables\CustomFields;

/**
 * Declarative column/filter/action catalogue for the `custom-fields` admin
 * domain (spec 0021 — ADMIN CRUD DEFINIZIONI). Extracted out of
 * CustomFieldsTableDefinition (file-size split, engineering.md §6): pure data
 * (no logic), mirroring AttributeColumnCatalog.
 *
 * Every column is a real DB column on `custom_field_definitions`. `type` is
 * rendered as a badge, driven by FieldTypeRegistry (config/custom-fields.php),
 * not a PHP enum (the type catalogue is deliberately config-driven for OCP).
 */
final class CustomFieldColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'entity_type',
                'label' => 'customFields.columns.entity_type',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'key',
                'label' => 'customFields.columns.key',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'label',
                'label' => 'customFields.columns.label',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
                'searchable' => true,
            ],
            [
                'id' => 'type',
                'label' => 'customFields.columns.type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'group',
                'label' => 'customFields.columns.group',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'is_active',
                'label' => 'customFields.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'customFields.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'entity_type', 'type' => 'set'],
            ['columnId' => 'key', 'type' => 'text'],
            ['columnId' => 'type', 'type' => 'set'],
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
                'permission' => 'custom-fields.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'custom-fields.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'custom-fields.viewActivity',
            ],
        ];
    }
}
