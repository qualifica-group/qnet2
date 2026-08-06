<?php

namespace App\Http\Requests\ProductCategories;

use App\Enums\AttributeContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for
 * GET /api/product-categories/{productCategory}/effective-attributes (spec
 * 0061): `context` selects which usage-context slice of the category's
 * effective attributes to resolve — 'product' (the Product card's dynamic
 * fields) or 'quote' (the Offerta's "Informazioni aggiuntive", spec 0084).
 * REQUIRED: the former implicit 'opportunity' default died with that context,
 * and a silently defaulted slice is exactly how it survived unread for a
 * release. Authorization is intentionally NOT handled here (it stays in the
 * controller, a cross-resource rule).
 */
class EffectiveAttributesRequest extends FormRequest
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
        ];
    }

    public function context(): AttributeContext
    {
        return AttributeContext::from($this->validated('context'));
    }
}
