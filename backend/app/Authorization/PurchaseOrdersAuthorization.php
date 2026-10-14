<?php

declare(strict_types=1);

namespace App\Authorization;

use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `purchase-orders` resource (spec 0210, D-20).
 * The header fields are writable by whoever may write while the order is a
 * draft or in validation (D-10), and past approval only the service fields
 * until the order is cancelled (spec 0259); totals, number and status are set by the
 * services and are not in the catalogue. Only the five fields the form always
 * requires are mandatory (locked in the Role matrix).
 */
class PurchaseOrdersAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'purchase-orders';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('supplier_id', 'select', mandatory: true),
            new FieldDefinition('company_id', 'select', mandatory: true),
            new FieldDefinition('company_site_id', 'select', mandatory: true),
            new FieldDefinition('order_date', 'date', mandatory: true),
            new FieldDefinition('description', 'text', mandatory: true),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('payment_method_id', 'select'),
            new FieldDefinition('is_internal_user', 'boolean'),
            new FieldDefinition('customer_id', 'select'),
            new FieldDefinition('work_order_id', 'select'),
            new FieldDefinition('quote_id', 'select'),
            new FieldDefinition('opportunity_id', 'select'),
            new FieldDefinition('sector_ids', 'select'),
            new FieldDefinition('sector_notes', 'text'),
            new FieldDefinition('general_notes', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'merge', 'send', 'export', 'view_activity'];
    }

    /**
     * A cancelled order is read-only; past approval the service fields stay
     * writable (spec 0259, D-2), so `update` holds until then.
     *
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        $permissions = parent::resourcePermissions($actor, $model);

        if ($this->isCancelled($model)) {
            $permissions['update'] = false;
        }

        return $permissions;
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model) && ! $this->isCancelled($model);
        $serviceOnly = $this->isLocked($model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            // The numbering is per company: it is fixed once the order exists (spec 0244, D-1)
            $fixed = $field->key === 'company_id' && $model?->exists === true;
            // Past approval only the service fields stay open (spec 0259, D-2)
            $fixed = $fixed || ($serviceOnly && ! in_array($field->key, PurchaseOrderData::SERVICE_FIELDS, true));
            $permissions[$field->key] = $mayWrite && ! $fixed
                ? FieldPermission::visibleEditable(required: $field->mandatory)
                : FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $editable = $model !== null && ! $this->isLocked($model);

        return [
            'delete' => $editable && $actor->can('purchase-orders.delete'),
            'merge' => $editable && $actor->can('purchase-orders.merge'),
            'send' => $model !== null && $model->status?->isSendable() === true && $actor->can('purchase-orders.send'),
            'export' => $actor->can('purchase-orders.export'),
            'view_activity' => $model !== null && $actor->can('purchase-orders.viewActivity'),
        ];
    }

    private function isCancelled(?Model $model): bool
    {
        return $model instanceof PurchaseOrder && $model->status !== null && ! $model->status->allowsServiceEdits();
    }

    private function isLocked(?Model $model): bool
    {
        // The table /columns endpoint passes an unsaved instance with no status:
        // nothing is locked until a persisted order reports a non-editable one.
        return $model instanceof PurchaseOrder && $model->status !== null && ! $model->status->isEditable();
    }
}
