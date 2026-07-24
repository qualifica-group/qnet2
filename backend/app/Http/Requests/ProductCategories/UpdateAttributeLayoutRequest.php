<?php

namespace App\Http\Requests\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for
 * PUT /api/product-categories/{productCategory}/attribute-layouts (spec
 * 0062, data_contract). `layout` gets only a shallow `array`/`nullable`
 * check here — the deep shape rules (enums, limits, structure) AND the
 * category-scoped allow-list check both need per-category effective
 * attributes, data a static rules() array cannot see, so they live in
 * App\Services\ProductCategories\AttributeLayoutValidator, invoked by
 * AttributeLayoutService::upsert() (mirrors the `attribute_values` split in
 * UpdateProductRequest / UpdateRequestRequest). Authorization is
 * intentionally NOT handled here (it stays in the controller via
 * authorize('update', $productCategory)).
 */
class UpdateAttributeLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', Rule::enum(AttributeContext::class)],
            'form_mode' => ['required', Rule::enum(FormMode::class)],
            'layout' => ['nullable', 'array'],
        ];
    }

    public function context(): AttributeContext
    {
        return AttributeContext::from($this->validated('context'));
    }

    public function formMode(): FormMode
    {
        return FormMode::from($this->validated('form_mode'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function layout(): ?array
    {
        return $this->validated('layout');
    }
}
