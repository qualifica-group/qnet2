<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `contract-statuses` resource (spec 0072).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * RewardStatusesAuthorization/QuoteStatusesAuthorization. `color` is
 * mandatory (BR-5's precedent, RewardStatusesAuthorization). `sort_order` is
 * server-managed, no longer writable via the API. `group`
 * (App\Enums\ContractStatusGroup) is the fixed classification
 * (open/pending/closed_won/closed_lost). `is_default`'s BR-5 transition
 * rules live in App\Services\Contracts\ContractStatusDefaultManager, not
 * here — this class only decides visibility/editability, never the
 * business-rule validity of a submitted value.
 */
class ContractStatusesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'contract-statuses';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('color', 'color', mandatory: true),
            new FieldDefinition('group', 'select'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('is_default', 'boolean'),
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
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'color' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'group' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_default' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('contract-statuses.delete'),
            'export' => $actor->can('contract-statuses.export'),
            'import' => $actor->can('contract-statuses.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `contract-statuses.view` boundary is enforced
            // separately by GET /api/activity-log/contract-statuses/{id}.
            'view_activity' => $model !== null && $actor->can('contract-statuses.viewActivity'),
        ];
    }
}
