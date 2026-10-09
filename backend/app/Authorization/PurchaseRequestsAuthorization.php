<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `purchase-requests` resource (spec 0208). The
 * header and footer fields are writable by whoever may write, until the request
 * is closed; totals, status and closure data are set by services and are not in
 * the catalogue. Only the first three fields are mandatory (locked in the Role
 * matrix): the others, `company_id` included, can be restricted per role.
 */
class PurchaseRequestsAuthorization extends AbstractResourceAuthorization
{
    /** Header fields the form requires on top of the mandatory ones. */
    private const array REQUIRED_FIELDS = [
        'requester_id', 'function_manager_id', 'company_id', 'company_site_id',
        'operational_site_id', 'business_function_id',
    ];

    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'purchase-requests';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('subject', 'text', mandatory: true),
            new FieldDefinition('requested_at', 'date', mandatory: true),
            new FieldDefinition('priority', 'select', mandatory: true),
            new FieldDefinition('requester_id', 'select'),
            new FieldDefinition('function_manager_id', 'select'),
            new FieldDefinition('customer_id', 'select'),
            new FieldDefinition('supplier_id', 'select'),
            new FieldDefinition('work_order_id', 'select'),
            new FieldDefinition('company_id', 'select'),
            new FieldDefinition('company_site_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('business_function_id', 'select'),
            new FieldDefinition('notes', 'textarea'),
            new FieldDefinition('delivery_terms', 'textarea'),
            new FieldDefinition('procurement_plan', 'textarea'),
            new FieldDefinition('technical_requirements', 'textarea'),
            new FieldDefinition('special_conditions', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'close', 'notify_manager', 'export', 'view_activity'];
    }

    /**
     * The request is read-only once closed (spec 0208, D-10).
     *
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        $permissions = parent::resourcePermissions($actor, $model);

        if ($this->isClosed($model)) {
            $permissions['update'] = false;
        }

        return $permissions;
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model) && ! $this->isClosed($model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            $required = $field->mandatory || in_array($field->key, self::REQUIRED_FIELDS, true);

            $permissions[$field->key] = $mayWrite
                ? FieldPermission::visibleEditable(required: $required)
                : FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $open = $model !== null && ! $this->isClosed($model);

        return [
            'delete' => $model !== null && $actor->can('purchase-requests.delete'),
            'close' => $open && $actor->can('purchase-requests.close'),
            'notify_manager' => $open && $actor->can('purchase-requests.update'),
            'export' => $actor->can('purchase-requests.export'),
            'view_activity' => $model !== null && $actor->can('purchase-requests.viewActivity'),
        ];
    }

    private function isClosed(?Model $model): bool
    {
        return $model instanceof PurchaseRequest && $model->isClosed();
    }
}
