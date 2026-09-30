<?php

declare(strict_types=1);

namespace App\Tables\EmailTemplates;

/**
 * Declarative column/filter/action catalogue for the `email-templates`
 * domain (spec 0175, D-14). Extracted out of EmailTemplatesTableDefinition
 * (file-size split, engineering.md §6): pure data, no logic. Every column is
 * a real DB column handled entirely by the generic engine.
 *
 * `module` is neither sortable nor filterable: D-10 ships a single case
 * (`work_orders`), so ordering/filtering on it is meaningless today (mirrors
 * TaskImportanceColumnCatalog's own choice for `color`/`icon`). `subject`/
 * `description` are filterable free text, not sortable.
 */
final class EmailTemplateColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'emailTemplates.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'module',
                'label' => 'emailTemplates.columns.module',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'subject',
                'label' => 'emailTemplates.columns.subject',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'emailTemplates.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'is_active',
                'label' => 'emailTemplates.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'emailTemplates.columns.created_at',
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
            ['columnId' => 'subject', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
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
                'permission' => 'email-templates.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'email-templates.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'email-templates.viewActivity',
            ],
        ];
    }
}
