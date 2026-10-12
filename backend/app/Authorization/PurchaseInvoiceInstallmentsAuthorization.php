<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `purchase-invoice-installments` resource (spec 0211).
 * Only the due date and the payment method code can be edited; amounts and the
 * payment state are produced by the document and the payment flow, so they
 * are visible-readonly for everyone and the Role matrix can only hide them.
 */
class PurchaseInvoiceInstallmentsAuthorization extends AbstractResourceAuthorization
{
    /** @var array<int, string> */
    private const array EDITABLE_FIELDS = ['due_date', 'payment_method_code'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'purchase-invoice-installments';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('due_date', 'date', mandatory: true),
            new FieldDefinition('payment_method_code', 'select'),
            new FieldDefinition('amount', 'number'),
            new FieldDefinition('paid_amount', 'number'),
            new FieldDefinition('paid_at', 'date'),
            new FieldDefinition('residual_amount', 'number'),
            new FieldDefinition('financial_account', 'select'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['export'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            $editable = $mayWrite && in_array($field->key, self::EDITABLE_FIELDS, true);

            $permissions[$field->key] = match (true) {
                ! $editable => FieldPermission::visibleReadonly(),
                $field->mandatory => FieldPermission::visibleEditable(required: true),
                default => FieldPermission::visibleEditable(),
            };
        }

        return $permissions;
    }
}
