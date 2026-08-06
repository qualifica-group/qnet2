<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesAttributeContextAssignments;
use App\Http\Requests\Concerns\ValidatesManagerLabelPositions;
use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/product-categories/{productCategory}
 * (spec 0017; spec 0061 for `attributes.*.context`). Every field is
 * `sometimes` to support partial PATCH updates: `attributes`, when
 * submitted, is a full-replace sync. The anti-cycle guard (parent_id cannot
 * be the category itself or one of its own descendants) is enforced by
 * ProductCategoryService, not here (it needs to walk the tree).
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $productCategory)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 */
class UpdateProductCategoryRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesAttributeContextAssignments, ValidatesManagerLabelPositions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductCategoryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'inherits_product_attributes' => ['sometimes', 'boolean'],
            // Spec 0084: the third usage context's own inheritance barrier.
            'inherits_quote_attributes' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'business_function_id' => ['sometimes', 'nullable', 'integer', 'exists:business_functions,id'],
            // Only meaningful on a ROOT category: under a parent the value is
            // inherited, and ProductCategoryService refuses a divergent one.
            'requires_quote' => ['sometimes', 'boolean'],
            // Spec 0074: whether the category may be picked as a
            // classification target. Never inherited, so no guard here.
            'is_selectable' => ['sometimes', 'boolean'],
            // Spec 0077: same root-only semantics as requires_quote — a
            // reparent (parent_id changes) or an edit of the mode itself
            // triggers ProductCategoryService's subtree resync.
            'management_mode' => ['sometimes', Rule::enum(CategoryManagementMode::class)],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.context' => ['required', Rule::enum(AttributeContext::class)],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer'],
            // Spec 0080: sparse position->label map for the "Gestori Account"
            // section. Key range (1..MANAGER_LABEL_MAX_POSITION) is checked by
            // ValidatesManagerLabelPositions, called from withValidator().
            'manager_labels' => ['sometimes', 'nullable', 'array'],
            'manager_labels.*' => ['nullable', 'string', 'max:'.ProductCategory::MANAGER_LABEL_MAX_LENGTH],
            'inherits_manager_labels' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAttributeContextAssignments($validator);
            $this->validateManagerLabelPositions($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'product-categories';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $this->route('productCategory');

        return $productCategory;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProductCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProductCategoryData::fromValidated($validated);
    }
}
