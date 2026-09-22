<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Enums\AdvancedFilterType;
use App\Enums\TaskListStatus;

/**
 * Advanced-filter catalogue for the `tasks` domain (spec 0147): the same axes
 * as the work-order Task board's filter sheet (spec 0146). `status`, `due`
 * and `assignment` are derived and applied by TaskAdvancedFilterApplier; every
 * `relation` entry targets a real Task relation, matched by id by the generic
 * AdvancedFilterApplier.
 *
 * `status` is `required` with `defaultValue: open` (D-2): the frontend omits a
 * value equal to its default, and the generic engine then falls back to it, so
 * the table opens on the open tasks exactly like the board.
 */
final class TaskAdvancedFilterCatalog
{
    public const string STATUS = 'status';

    public const string DUE = 'due';

    public const string ASSIGNMENT = 'assignment';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            self::enumFilter(self::STATUS, 'status', 1, 'task_list_status', TaskListStatus::Open->value),
            self::enumFilter(self::DUE, 'due', 2, 'task_due_window'),
            self::enumFilter(self::ASSIGNMENT, 'assignment', 3, 'task_assignment_scope'),
            self::relationFilter('task_status', 'taskStatus', 4, 'task-statuses', 'taskStatus'),
            self::relationFilter('task_type', 'taskType', 5, 'task-types', 'taskType'),
            self::relationFilter('task_priority', 'taskPriority', 6, 'task-priorities', 'taskPriority'),
            self::relationFilter('task_importance', 'taskImportance', 7, 'task-importances', 'taskImportance'),
            self::relationFilter('requester', 'requester', 8, 'users', 'requester'),
            self::relationFilter('assignees', 'assignees', 9, 'users', 'assignees'),
            self::relationFilter('watchers', 'watchers', 10, 'users', 'watchers'),
        ];
    }

    /**
     * A filter with a default is `required`, so the engine falls back to that
     * default whenever the request omits it.
     *
     * @return array<string, mixed>
     */
    private static function enumFilter(string $name, string $labelKey, int $order, string $enumKey, ?string $defaultValue = null): array
    {
        $descriptor = [
            'name' => $name,
            'label' => "tasks.advancedFilters.{$labelKey}",
            'type' => AdvancedFilterType::Enum,
            'order' => $order,
            'required' => $defaultValue !== null,
            'visible' => true,
            'width' => 'md',
            'multiple' => false,
            'target' => $name,
            'enumKey' => $enumKey,
        ];

        if ($defaultValue !== null) {
            $descriptor['defaultValue'] = $defaultValue;
        }

        return $descriptor;
    }

    /**
     * @return array<string, mixed>
     */
    private static function relationFilter(string $name, string $labelKey, int $order, string $resource, string $relation): array
    {
        return [
            'name' => $name,
            'label' => "tasks.advancedFilters.{$labelKey}",
            'type' => AdvancedFilterType::Relation,
            'order' => $order,
            'required' => false,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'source' => ['resource' => $resource],
            'target' => $relation,
        ];
    }
}
