<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `support-topics` resource (spec 0334): every
 * field is visible+editable when the actor may write, else visible+readonly
 * (same shape as TaskCategoriesAuthorization). `sort_order` is a plain
 * editable number here, unlike the Task lookups, so it is part of fields().
 */
class SupportTopicsAuthorization extends AbstractResourceAuthorization
{
    private const array FIELDS = [
        'name' => ['text', true],
        'description' => ['textarea', false],
        'icon' => ['text', false],
        'sort_order' => ['number', false],
        'is_active' => ['boolean', false],
        'task_type_id' => ['select', false],
        'task_category_id' => ['select', false],
        'task_priority_id' => ['select', false],
        'urgent_task_priority_id' => ['select', false],
        'task_importance_id' => ['select', false],
        'due_days' => ['number', true],
        'urgent_due_days' => ['number', false],
        'is_private' => ['boolean', false],
        'requires_closure_feedback' => ['boolean', false],
        'assignee_ids' => ['select', true],
        'watcher_ids' => ['select', false],
    ];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'support-topics';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $fields = [];

        foreach (self::FIELDS as $key => [$type, $mandatory]) {
            $fields[] = new FieldDefinition($key, $type, mandatory: $mandatory);
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $ceiling = [];

        foreach (self::FIELDS as $key => [, $mandatory]) {
            $ceiling[$key] = $mayWrite
                ? FieldPermission::visibleEditable(required: $mandatory)
                : FieldPermission::visibleReadonly();
        }

        return $ceiling;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('support-topics.delete'),
            'view_activity' => $model !== null && $actor->can('support-topics.viewActivity'),
        ];
    }
}
