<?php

namespace App\Http\Requests\Products;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Rules\SelectableProductCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/products (spec 0017; spec 0061 for
 * `attribute_values`; spec 0065, D-1b for `code`). `attribute_values` gets
 * only a shallow `array` check here — its DEEP validation (per-code
 * applicability/type/required against the product's PRODUCT-context
 * effective attributes) runs in ProductService via the SAME
 * AttributeValueValidator the Opportunity path uses (mirrors
 * UpdateRequestRequest's docblock: doing it twice would mean resolving
 * CategoryHierarchy::effectiveAttributes() an extra time for no benefit).
 *
 * `code` is optional: when absent, null or empty, the Service falls back to
 * the sequential PRD-0001 generator; when submitted, it must be unique
 * against `products.code` (mirrors StoreProjectRequest, spec 0025).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Product::class)). EnforcesFieldPermissions
 * (spec 0004) rejects any submitted field the actor cannot edit (create
 * context, model = null).
 */
class StoreProductRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:32', Rule::unique('products', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'cost' => ['required', 'numeric'],
            'price' => ['required', 'numeric'],
            'category_id' => ['required', 'integer', new SelectableProductCategory],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'vat_rate_id' => ['nullable', 'integer', 'exists:vat_rates,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:registries,id'],
            // Spec 0088, D-4: absent/null falls back to the default unit in
            // ProductService, so `sometimes` (not `nullable` alone) matters —
            // it is what lets CreateProductData tell "not submitted" from
            // "submitted null" apart via array_key_exists().
            'unit_of_measure_id' => ['sometimes', 'nullable', 'integer', 'exists:units_of_measure,id'],
            'attribute_values' => ['sometimes', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'products';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateProductData::fromValidated($validated);
    }
}
