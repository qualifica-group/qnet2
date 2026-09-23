<?php

namespace App\Tables\Attributes;

/**
 * Declarative column/filter/action catalogue for the `attributes` domain
 * (spec 0017, aligned to the custom fields' presentation shape — spec 0021).
 * Extracted out of AttributesTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring CustomFieldColumnCatalog.
 *
 * Every column is a real DB column except `options_count` (a withCount()
 * aggregate attached in AttributesTableDefinition::baseQuery), which is not
 * part of the frozen column contract and only rides along in mapRow().
 * `type` is rendered as a badge, driven by FieldTypeRegistry
 * (config/custom-fields.php), not a PHP enum (the type catalogue is
 * deliberately config-driven for OCP).
 */
final class AttributeColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'attributes.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'name',
                'label' => 'attributes.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'type',
                'label' => 'attributes.columns.type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'attributes.columns.created_at',
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
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'type', 'type' => 'set'],
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
                'permission' => 'attributes.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'attributes.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'attributes.delete',
            ],
            [
                'key' => 'duplicate',
                'label' => 'actions.duplicate',
                'icon' => 'copy',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'attributes.create',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'attributes.viewActivity',
            ],
        ];
    }
}
