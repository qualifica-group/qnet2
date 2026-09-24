<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Enums\AdvancedFilterType;
use App\Enums\TaskAssignmentScope;
use App\Enums\TaskListStatus;
use App\Models\User;
use App\Services\Tasks\TaskVisibilityScope;

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
 *
 * `assignment` (spec 0153, D-1) is `multiselect` rather than a single `enum`:
 * REQUIRED with `defaultValue: [assigned_to_me]`, so an omitted value (or an
 * explicit `[]`, which `AdvancedFilterApplier::isValidValue()` rejects as a
 * 422 — a non-empty scalar list is the type's own structural rule) can never
 * leave the list effectively unscoped.
 */
final class TaskAdvancedFilterCatalog
{
    public const string STATUS = 'status';

    public const string DUE = 'due';

    public const string ASSIGNMENT = 'assignment';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(?User $actor = null): array
    {
        return [
            self::enumFilter(self::STATUS, 'status', 1, 'task_list_status', TaskListStatus::Open->value),
            self::enumFilter(self::DUE, 'due', 2, 'task_due_window'),
            self::assignmentFilter($actor),
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
     * `assignment` (spec 0153, D-1): required, multi-value, OR-combined by
     * TaskAdvancedFilterApplier, defaulting to `[assigned_to_me]`. `visible`
     * is listed in `excludedValues` for an actor without viewAll/viewSite:
     * for them it would coincide with `all`, so the option is only noise.
     *
     * @return array<string, mixed>
     */
    private static function assignmentFilter(?User $actor): array
    {
        $canSeeBeyondRoles = $actor !== null
            && ($actor->can(TaskVisibilityScope::VIEW_ALL_PERMISSION) || $actor->can(TaskVisibilityScope::VIEW_SITE_PERMISSION));

        return [
            'name' => self::ASSIGNMENT,
            'label' => 'tasks.advancedFilters.assignment',
            'type' => AdvancedFilterType::Enum,
            'order' => 3,
            'required' => true,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'target' => self::ASSIGNMENT,
            'enumKey' => 'task_assignment_scope',
            'defaultValue' => [TaskAssignmentScope::AssignedToMe->value],
            'excludedValues' => $canSeeBeyondRoles ? [] : [TaskAssignmentScope::Visible->value],
        ];
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
