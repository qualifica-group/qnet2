<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `contracts` resource (spec 0072, MT-02).
 *
 * `accepted_at`/`validated_at`/`terminated_at`/`termination_reason` are
 * ALWAYS `visibleReadonly()` (D-7/BR-3/BR-4): they are written exclusively by
 * the domain actions (MT-03), never by this PATCH, so no write ability ever
 * unlocks them here. Every other field follows the standard
 * `actorMayWrite()` ceiling (create is meaningless for this resource — D-6 —
 * so in practice this is always the `update` ability).
 *
 * `reactivate` (BR-2/D-3) is additionally gated on the model actually being
 * suspended: offering the action on a non-suspended contract would let a
 * client invoke an endpoint that always 422s.
 */
class ContractsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'contracts';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('contract_status_id', 'select', mandatory: true),
            new FieldDefinition('accepted_at', 'date'),
            new FieldDefinition('validated_at', 'date'),
            new FieldDefinition('renewal_date', 'date'),
            new FieldDefinition('expiry_date', 'date'),
            new FieldDefinition('terminated_at', 'date'),
            new FieldDefinition('termination_reason', 'textarea'),
            new FieldDefinition('payment_notes', 'textarea'),
            new FieldDefinition('comments', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['validate', 'terminate', 'schedule', 'change_status', 'reactivate', 'export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'contract_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            // Written only by the domain actions (MT-03), never by a PATCH.
            'accepted_at' => FieldPermission::visibleReadonly(),
            'validated_at' => FieldPermission::visibleReadonly(),
            'renewal_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'expiry_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'terminated_at' => FieldPermission::visibleReadonly(),
            'termination_reason' => FieldPermission::visibleReadonly(),
            'payment_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'comments' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'validate' => $model !== null && $actor->can('contracts.validate'),
            'terminate' => $model !== null && $actor->can('contracts.terminate'),
            'schedule' => $model !== null && $actor->can('contracts.schedule'),
            'change_status' => $model !== null && $actor->can('contracts.changeStatus'),
            'reactivate' => $model instanceof Contract && $model->isSuspended() && $actor->can('contracts.reactivate'),
            'export' => $actor->can('contracts.export'),
            'view_activity' => $model !== null && $actor->can('contracts.viewActivity'),
        ];
    }
}
