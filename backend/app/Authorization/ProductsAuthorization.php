<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `products` resource (spec 0017).
 *
 * Covers ONLY the generic fields (name/description/cost/price/category_id/product_type):
 * dynamic attributes are authorized at the resource level (products.update),
 * never per-field (spec 0017 decision — no field-permission granularity on
 * EAV values). No contextual rules otherwise: every field's ceiling is
 * simply visible+editable when the actor may write, else visible+readonly.
 */
class ProductsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'products';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('cost', 'number', mandatory: true),
            new FieldDefinition('price', 'number', mandatory: true),
            new FieldDefinition('category_id', 'select', mandatory: true),
            new FieldDefinition('product_type', 'select', mandatory: true),
            new FieldDefinition('vat_rate_id', 'select'),
            new FieldDefinition('supplier_id', 'select'),
            new FieldDefinition('state_id', 'select'),
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
            'cost' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'price' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'category_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'product_type' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'vat_rate_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'supplier_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('products.delete'),
            'export' => $actor->can('products.export'),
            'import' => $actor->can('products.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `products.view` boundary is enforced separately by
            // GET /api/activity-log/products/{id} itself.
            'view_activity' => $model !== null && $actor->can('products.viewActivity'),
        ];
    }
}
