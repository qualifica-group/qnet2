<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `commission-settlements` resource (spec 0213,
 * D-8). The notes are editable only while the settlement is a draft and the
 * actor may update; every computed amount is visible-readonly and the Role
 * matrix can only hide it.
 */
class CommissionSettlementsAuthorization extends AbstractResourceAuthorization
{
    /** Computed amounts: never editable, hideable per role. */
    private const array AMOUNT_FIELDS = [
        'taxable_amount', 'vat_amount', 'withholding_amount',
        'enasarco_agent_amount', 'enasarco_principal_amount', 'net_payable',
    ];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'commission-settlements';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('notes', 'textarea'),
            ...array_map(fn (string $key): FieldDefinition => new FieldDefinition($key, 'number'), self::AMOUNT_FIELDS),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['recalculate', 'issue', 'cancel', 'delete', 'adjust', 'export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $permissions = ['notes' => $this->notesPermission($actor, $model)];

        foreach (self::AMOUNT_FIELDS as $key) {
            $permissions[$key] = FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $onRecord = $model !== null;

        return [
            'recalculate' => $onRecord && $actor->can('commission-settlements.update'),
            'issue' => $onRecord && $actor->can('commission-settlements.issue'),
            'cancel' => $onRecord && $actor->can('commission-settlements.cancel'),
            'delete' => $onRecord && $actor->can('commission-settlements.delete'),
            'adjust' => $actor->can('commission-settlements.adjust'),
            'export' => $actor->can('commission-settlements.export'),
            'view_activity' => $onRecord && $actor->can('commission-settlements.viewActivity'),
        ];
    }

    private function notesPermission(User $actor, ?Model $model): FieldPermission
    {
        $editable = $model instanceof CommissionSettlement
            && $model->status === CommissionSettlementStatus::Draft
            && $actor->can('commission-settlements.update');

        return $editable ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();
    }
}
