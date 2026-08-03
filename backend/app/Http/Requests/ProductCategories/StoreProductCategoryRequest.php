<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Enums\AttributeContext;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesAttributeContextAssignments;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/product-categories (spec 0017; spec
 * 0061 for `attributes.*.context`).
 *
 * A cycle is structurally impossible on create (the category has no id yet),
 * so no cycle guard is needed here (unlike UpdateProductCategoryRequest's
 * companion Service-level guard). Authorization is intentionally NOT handled
 * here (it stays in the controller via authorize('create',
 * ProductCategory::class)). EnforcesFieldPermissions (spec 0004) additionally
 * rejects any submitted field the actor cannot edit (create context, model =
 * null).
 */
class StoreProductCategoryRequest extends FormRequest
{
    use EnforcesFieldPermissions, ValidatesAttributeContextAssignments;

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
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'inherits_product_attributes' => ['sometimes', 'boolean'],
            'inherits_opportunity_attributes' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
            // Only meaningful on a ROOT category: under a parent the value is
            // inherited, and ProductCategoryService refuses a divergent one.
            'requires_quote' => ['sometimes', 'boolean'],
            // Spec 0074: whether the category may be picked as a
            // classification target. Omitted = true (selectable).
            'is_selectable' => ['sometimes', 'boolean'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'attributes.*.context' => ['required', Rule::enum(AttributeContext::class)],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateAttributeContextAssignments($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'product-categories';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProductCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProductCategoryData::fromValidated($validated);
    }
}
