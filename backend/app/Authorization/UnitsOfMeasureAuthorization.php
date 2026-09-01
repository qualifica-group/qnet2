<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `units-of-measure` resource (spec 0088).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` (D-1), writable
 * ONLY in create ($model === null); once persisted it is permanently
 * readonly regardless of write ability, mirroring PaymentMethodsAuthorization's
 * own `code` ceiling.
 */
class UnitsOfMeasureAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'units-of-measure';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('symbol', 'text', mandatory: true),
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
            // code is writable only in create (D-1): permanently readonly
            // once a $model exists, regardless of write ability — the
            // immutability guard is enforced ahead of this ceiling by
            // UpdateUnitOfMeasureRequest's own `prohibited` rule.
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'symbol' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('units-of-measure.delete'),
            'export' => $actor->can('units-of-measure.export'),
            'import' => $actor->can('units-of-measure.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `units-of-measure.view` boundary is enforced
            // separately by GET /api/activity-log/units-of-measure/{id}.
            'view_activity' => $model !== null && $actor->can('units-of-measure.viewActivity'),
        ];
    }
}
