<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `payment-methods` resource (spec 0068).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` (D-3), writable
 * ONLY in create ($model === null); once persisted it is permanently
 * readonly regardless of write ability, mirroring ProductsAuthorization's
 * own `code` ceiling. `sort_order` is NOT a declared field: it is
 * server-managed and never appears in this catalogue.
 */
class PaymentMethodsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'payment-methods';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('code', 'text', mandatory: true),
            new FieldDefinition('payment_method_code', 'text'),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('payment_instructions', 'textarea'),
            new FieldDefinition('payment_days', 'number'),
            new FieldDefinition('is_active', 'boolean'),
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
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            // code is writable only in create (D-3): permanently readonly
            // once a $model exists, regardless of write ability — the
            // immutability guard is enforced ahead of this ceiling by
            // UpdatePaymentMethodRequest's own `prohibited` rule.
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'payment_method_code' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'payment_instructions' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'payment_days' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('payment-methods.delete'),
            'export' => $actor->can('payment-methods.export'),
            'import' => $actor->can('payment-methods.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `payment-methods.view` boundary is enforced
            // separately by GET /api/activity-log/payment-methods/{id}.
            'view_activity' => $model !== null && $actor->can('payment-methods.viewActivity'),
        ];
    }
}
