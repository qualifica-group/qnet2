<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `product-typologies` resource (spec 0099).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` (D-2), writable
 * ONLY in create ($model === null); once persisted it is permanently
 * readonly regardless of write ability, mirroring
 * UnitsOfMeasureAuthorization's own `code` ceiling.
 */
class ProductTypologiesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'product-typologies';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            // code is writable only in create (D-2): permanently readonly
            // once a $model exists, regardless of write ability — the
            // immutability guard is enforced ahead of this ceiling by
            // UpdateProductTypologyRequest's own `prohibited` rule.
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('product-typologies.delete'),
            'export' => $actor->can('product-typologies.export'),
            'import' => $actor->can('product-typologies.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `product-typologies.view` boundary is enforced
            // separately by GET /api/activity-log/product-typologies/{id}.
            'view_activity' => $model !== null && $actor->can('product-typologies.viewActivity'),
        ];
    }
}
