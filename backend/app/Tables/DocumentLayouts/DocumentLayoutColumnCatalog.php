<?php

namespace App\Tables\DocumentLayouts;

/**
 * Declarative column/filter/action catalogue for the `document-layouts`
 * domain (spec 0069, MT-5). Extracted out of DocumentLayoutsTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic). Every column
 * (name/code/module/is_default/is_active/description/created_at/updated_at)
 * is a real DB column handled entirely by the generic engine — order is
 * FROZEN by the spec (AC-070).
 *
 * `is_default` is declared WITHOUT `editable`: its change must go through
 * `App\Services\DocumentLayouts\DocumentLayoutDefaultManager`'s
 * transactional invariant (D-7, "un solo predefinito per modulo"), which a
 * bare mass-assignment update — the generic inline-edit pipeline's default
 * write, `updateCell()` — would bypass entirely (it never calls the
 * Service). `is_active` IS editable, but
 * `DocumentLayoutsTableDefinition::updateCell()` overrides the default write
 * to route it through the SAME guard (D-7d) before persisting.
 */
final class DocumentLayoutColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'documentLayouts.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'code',
                'label' => 'documentLayouts.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'module',
                'label' => 'documentLayouts.columns.module',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                // NOT editable inline (D-7, see class docblock): the client
                // must go through the regular PATCH /document-layouts/{id}
                // endpoint (DocumentLayoutService::update()), never the
                // generic per-cell mass-assignment path.
                'id' => 'is_default',
                'label' => 'documentLayouts.columns.is_default',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'is_active',
                'label' => 'documentLayouts.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
                // Inline cell-editing (spec 0053): real, fillable, NOT
                // nullable column -> rule resolves to required|boolean. The
                // D-7d guard (predefinito cannot be deactivated) is enforced
                // by DocumentLayoutsTableDefinition::updateCell(), not here.
                'editable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'documentLayouts.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'created_at',
                'label' => 'documentLayouts.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'updated_at',
                'label' => 'documentLayouts.columns.updated_at',
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
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'module', 'type' => 'text'],
            ['columnId' => 'is_default', 'type' => 'boolean'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'updated_at', 'type' => 'date'],
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
                'permission' => 'document-layouts.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'document-layouts.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'document-layouts.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'document-layouts.viewActivity',
            ],
        ];
    }
}
