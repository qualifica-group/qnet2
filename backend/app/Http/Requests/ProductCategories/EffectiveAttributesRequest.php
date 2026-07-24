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
 * fields) or 'opportunity' (request-management preliminary info, the
 * pre-existing default — spec 0061 hard invariant: an absent `context`
 * resolves EXACTLY as before this feature). Authorization is intentionally
 * NOT handled here (it stays in the controller, a cross-resource rule).
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
            'context' => ['sometimes', Rule::enum(AttributeContext::class)],
        ];
    }

    public function context(): AttributeContext
    {
        $value = $this->validated('context');

        return $value === null ? AttributeContext::Opportunity : AttributeContext::from($value);
    }
}
