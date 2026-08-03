<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `product-categories` resource (spec 0017).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly. `attributes`
 * is a nested, custom-rendered editor (attribute_id/is_required/sort_order
 * rows); inherited attributes are read-only metadata, never submitted here.
 *
 * `requires_quote` is deliberately NOT narrowed here even though only a ROOT
 * category authors it: whether the flag is inherited depends on the SUBMITTED
 * parent (a child being promoted to root in the same save owns it from that
 * request on), which this ceiling cannot see. The no-override guard in
 * ProductCategoryService, which does see it, is the authority — mirroring how
 * `business_function_id` is handled.
 */
class ProductCategoriesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'product-categories';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('parent_id', 'select'),
            new FieldDefinition('inherits_product_attributes', 'boolean'),
            new FieldDefinition('inherits_opportunity_attributes', 'boolean'),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('business_function_id', 'select'),
            new FieldDefinition('requires_quote', 'boolean'),
            new FieldDefinition('is_selectable', 'boolean'),
            new FieldDefinition('attributes', 'custom'),
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
            'parent_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'inherits_product_attributes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'inherits_opportunity_attributes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'business_function_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'requires_quote' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_selectable' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attributes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('product-categories.delete'),
            'export' => $actor->can('product-categories.export'),
            'import' => $actor->can('product-categories.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `product-categories.view` boundary is enforced
            // separately by GET /api/activity-log/product-categories/{id}.
            'view_activity' => $model !== null && $actor->can('product-categories.viewActivity'),
        ];
    }
}
