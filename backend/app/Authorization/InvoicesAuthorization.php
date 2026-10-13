<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `invoices` resource (spec 0194). The header
 * fields are writable by whoever may write; amounts, number and type are set by
 * the service and never by the client, so they are not in the catalogue.
 */
class InvoicesAuthorization extends AbstractResourceAuthorization
{
    /** Header fields frozen once the invoice is registered. */
    private const array REGISTERED_READONLY_FIELDS = ['document_date', 'company_id', 'customer_registry_id', 'payment_method_id', 'financial_account_id', 'notes'];

    /** Fields that identify the registration on Fatture in Cloud. */
    private const array EXTERNAL_REFERENCE_FIELDS = ['external_number', 'external_date'];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'invoices';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('document_date', 'date', mandatory: true),
            new FieldDefinition('company_id', 'select', mandatory: true),
            new FieldDefinition('customer_registry_id', 'select', mandatory: true),
            new FieldDefinition('payment_method_id', 'select', mandatory: true),
            new FieldDefinition('financial_account_id', 'select'),
            new FieldDefinition('notes', 'textarea'),
            new FieldDefinition('internal_note', 'textarea'),
            new FieldDefinition('invoice_tag_id', 'select'),
            new FieldDefinition('external_number', 'text'),
            new FieldDefinition('external_date', 'date'),
            new FieldDefinition('deviation', 'number'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'downgrade', 'export', 'view_activity', 'collect', 'view_emails', 'send_email'];
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

        // A registered invoice (spec 0243, D-3): the header is frozen and the
        // external reference changes only with the downgrade permission.
        if ($model instanceof Invoice && $model->isRegistered()) {
            foreach (self::REGISTERED_READONLY_FIELDS as $key) {
                $permissions[$key] = FieldPermission::visibleReadonly();
            }

            foreach (self::EXTERNAL_REFERENCE_FIELDS as $key) {
                $permissions[$key] = $mayWrite && $actor->can('invoices.downgrade') ? $editable : FieldPermission::visibleReadonly();
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $registered = $model instanceof Invoice && $model->isRegistered();

        return [
            'delete' => $model !== null && ! $registered && $actor->can('invoices.delete'),
            'downgrade' => $registered && $actor->can('invoices.downgrade'),
            'export' => $actor->can('invoices.export'),
            'view_activity' => $model !== null && $actor->can('invoices.viewActivity'),
            'collect' => $model !== null && $actor->can('invoices.collect'),
            'view_emails' => $model !== null && $actor->can('invoices.viewEmails'),
            'send_email' => $model !== null && $actor->can('invoices.sendEmail'),
        ];
    }
}
