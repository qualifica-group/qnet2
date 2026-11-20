<?php

declare(strict_types=1);

namespace App\Tables\QuoteSystemStatuses;

use App\Enums\WorkflowStatusGroup;

/**
 * Column/filter/action catalogue of the `quote-system-statuses` domain (spec
 * 0404 part A). Pure data. `workflow_statuses_count` is a withCount alias:
 * shown, never sorted or filtered by the generic engine.
 */
final class QuoteSystemStatusColumnCatalog
{
    private const string RESOURCE = 'quote-system-statuses';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            ['id' => 'name', 'label' => 'quoteSystemStatuses.columns.name', 'type' => 'text', 'visible' => true,
                'sortable' => true, 'filterable' => true, 'filterType' => 'text', 'searchable' => true],
            ['id' => 'color', 'label' => 'quoteSystemStatuses.columns.color', 'type' => 'text', 'visible' => true,
                'sortable' => false, 'filterable' => false],
            ['id' => 'behavior', 'label' => 'quoteSystemStatuses.columns.behavior', 'type' => 'badge', 'visible' => true,
                'sortable' => true, 'filterable' => true, 'filterType' => 'set', 'options' => WorkflowStatusGroup::values()],
            ['id' => 'workflow_statuses_count', 'label' => 'quoteSystemStatuses.columns.workflowStatusesCount', 'type' => 'number',
                'visible' => true, 'sortable' => false, 'filterable' => false],
            ['id' => 'sort_order', 'label' => 'quoteSystemStatuses.columns.sortOrder', 'type' => 'number', 'visible' => true,
                'sortable' => true, 'filterable' => true, 'filterType' => 'number'],
            ['id' => 'created_at', 'label' => 'quoteSystemStatuses.columns.createdAt', 'type' => 'datetime', 'visible' => false,
                'sortable' => true, 'filterable' => true, 'filterType' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'behavior', 'type' => 'set', 'options' => WorkflowStatusGroup::values()],
            ['columnId' => 'sort_order', 'type' => 'number'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false,
                'permission' => self::RESOURCE.'.view'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true,
                'permission' => self::RESOURCE.'.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false,
                'permission' => self::RESOURCE.'.viewActivity'],
        ];
    }
}
