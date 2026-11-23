<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `document-checklist-categories` resource (spec 0410).
 * The resource key names the fields and the role matrix, but the permissions are
 * the `document-checklists.*` ones (D-2): no new permission exists for categories.
 */
class DocumentChecklistCategoriesAuthorization extends AbstractResourceAuthorization
{
    /** Permission prefix shared with the checklists (D-2). */
    private const string PERMISSION_PREFIX = 'document-checklists';

    public function resource(): string
    {
        return 'document-checklist-categories';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('sort_order', 'number'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'view_activity', 'sync'];
    }

    /**
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        return [
            'view' => $actor->can(self::PERMISSION_PREFIX.'.view'),
            'create' => $actor->can(self::PERMISSION_PREFIX.'.create'),
            'update' => $actor->can(self::PERMISSION_PREFIX.'.update'),
            'delete' => $actor->can(self::PERMISSION_PREFIX.'.delete'),
            'export' => false,
            'import' => false,
        ];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(required: true),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'sort_order' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can(self::PERMISSION_PREFIX.'.delete'),
            'view_activity' => $model !== null && $actor->can(self::PERMISSION_PREFIX.'.viewActivity'),
            'sync' => $model !== null && $actor->can(self::PERMISSION_PREFIX.'.update'),
        ];
    }

    protected function actorMayWrite(User $actor, ?Model $model): bool
    {
        return $actor->can(self::PERMISSION_PREFIX.($model === null ? '.create' : '.update'));
    }
}
