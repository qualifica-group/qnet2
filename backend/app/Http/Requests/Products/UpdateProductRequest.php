<?php

namespace App\Http\Requests\Products;

use App\DataObjects\Products\UpdateProductData;
use App\Enums\ProductType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Product;
use App\Rules\SelectableProductCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/products/{product} (spec 0017;
 * spec 0061 for `attribute_values`). Generic fields are `sometimes`.
 * `attribute_values` gets only a shallow `array` check here — see
 * StoreProductRequest's docblock for why its deep validation lives in
 * ProductService instead.
 *
 * `code` (spec 0065, D-1b) is deliberately ABSENT from `rules()`: it is
 * immutable once persisted. A submitted `code` that differs from the
 * persisted value is rejected with a 422 by EnforcesFieldPermissions (the
 * ceiling is readonly whenever `$model !== null`, see ProductsAuthorization);
 * resubmitting the identical value is a harmless no-op (mirrors
 * UpdateProjectRequest).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $product)).
 * EnforcesFieldPermissions (spec 0004) rejects any submitted field the actor
 * cannot edit on this specific model.
 */
class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'cost' => ['sometimes', 'required', 'numeric'],
            'price' => ['sometimes', 'required', 'numeric'],
            // Spec 0074 D-3b: the product's CURRENT category is exempt, so a
            // partial update that resubmits it unchanged still passes once
            // that category has been made unselectable.
            'category_id' => ['sometimes', 'required', 'integer', new SelectableProductCategory($this->currentCategoryIds())],
            'product_type' => ['sometimes', 'required', Rule::enum(ProductType::class)],
            'vat_rate_id' => ['sometimes', 'nullable', 'integer', 'exists:vat_rates,id'],
            'supplier_id' => ['sometimes', 'nullable', 'integer', 'exists:registries,id'],
            // Spec 0088, D-4: a submitted null resets to the default unit in
            // ProductService (the column is NOT NULL).
            'unit_of_measure_id' => ['sometimes', 'nullable', 'integer', 'exists:units_of_measure,id'],
            'product_typology_id' => ['sometimes', 'nullable', 'integer', 'exists:product_typologies,id'],
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

    /**
     * The category already persisted on the product being updated, exempt
     * from the selectable check (spec 0074 D-3b).
     *
     * @return array<int, int>
     */
    private function currentCategoryIds(): array
    {
        $product = $this->route('product');

        return $product instanceof Product && $product->category_id !== null
            ? [(int) $product->category_id]
            : [];
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $product;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProductData::fromValidated($validated);
    }
}
