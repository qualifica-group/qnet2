<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\PurchaseInvoices\LinkedSettlementGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `purchase-invoices` resource (spec 0250, D-9).
 * Same shape as the active invoices: amounts and type are set by the service
 * and never by the client, so they are not in the catalogue. A registered
 * invoice (type=invoice) freezes its header; the supplier number changes only
 * with the downgrade permission.
 */
class PurchaseInvoicesAuthorization extends AbstractResourceAuthorization
{
    /** Header fields frozen once the document is registered. */
    private const array REGISTERED_READONLY_FIELDS = [
        'document_date', 'company_id', 'supplier_id', 'payment_method_id', 'financial_account_id',
        'company_site_id', 'operational_site_id', 'cost_type_id', 'work_order_id',
    ];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository, private readonly LinkedSettlementGuard $settlementGuard)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'purchase-invoices';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('document_date', 'date', mandatory: true),
            new FieldDefinition('company_id', 'select', mandatory: true),
            new FieldDefinition('supplier_id', 'select', mandatory: true),
            new FieldDefinition('payment_method_id', 'select', mandatory: true),
            new FieldDefinition('financial_account_id', 'select'),
            new FieldDefinition('company_site_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('cost_type_id', 'select'),
            new FieldDefinition('work_order_id', 'select'),
            new FieldDefinition('document_number', 'text'),
            new FieldDefinition('notes', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['update', 'delete', 'details', 'downgrade', 'export', 'view_activity', 'pay'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $editable = $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();
        $required = $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly();
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = $field->mandatory ? $required : $editable;
        }

        if ($model instanceof PurchaseInvoice && $model->isRegistered()) {
            foreach (self::REGISTERED_READONLY_FIELDS as $key) {
                $permissions[$key] = FieldPermission::visibleReadonly();
            }

            $permissions['document_number'] = $mayWrite && $actor->can('purchase-invoices.downgrade') && ! $this->settlementGuard->isLinked($model)
                ? $editable
                : FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $registered = $model instanceof PurchaseInvoice && $model->isRegistered();
        $linked = $model instanceof PurchaseInvoice && $this->settlementGuard->isLinked($model);
        $deletable = $model instanceof PurchaseInvoice && ! $registered && ! $linked && ! $model->hasPayments();

        return [
            'update' => $model !== null && ! $registered && ! $linked && $actor->can('purchase-invoices.update'),
            'delete' => $deletable && $actor->can('purchase-invoices.delete'),
            'details' => $model !== null && $actor->can('purchase-invoices.update'),
            'downgrade' => $registered && ! $linked && $actor->can('purchase-invoices.downgrade'),
            'export' => $actor->can('purchase-invoices.export'),
            'view_activity' => $model !== null && $actor->can('purchase-invoices.viewActivity'),
            'pay' => $actor->can('purchase-invoices.pay'),
        ];
    }
}
