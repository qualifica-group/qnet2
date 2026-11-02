<?php

declare(strict_types=1);

namespace App\Tables\SupportTopics;

/**
 * Declarative column/filter/action catalogue for the `support-topics` domain
 * (spec 0334): pure data. `task_priority` and `assignees` are DERIVED display
 * columns (relation names), neither sortable nor filterable.
 */
final class SupportTopicColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'supportTopics.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::displayColumn('icon'),
            self::displayColumn('task_priority'),
            self::displayColumn('assignees'),
            [
                'id' => 'due_days',
                'label' => 'supportTopics.columns.due_days',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'sort_order',
                'label' => 'supportTopics.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'is_active',
                'label' => 'supportTopics.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
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
            ['columnId' => 'due_days', 'type' => 'number'],
            ['columnId' => 'sort_order', 'type' => 'number'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
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
                'permission' => 'support-topics.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'support-topics.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'support-topics.viewActivity',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function displayColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => "supportTopics.columns.{$id}",
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }
}
