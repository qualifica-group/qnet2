<?php

namespace App\Http\Requests\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for
 * GET /api/product-categories/{productCategory}/attribute-layouts (spec
 * 0062, data_contract): `context` defaults to Opportunity (same default as
 * the pre-existing effective-attributes endpoint), `form_mode` defaults to
 * Create. Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('view', $productCategory)).
 */
class AttributeLayoutQueryRequest extends FormRequest
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
            'form_mode' => ['sometimes', Rule::enum(FormMode::class)],
        ];
    }

    public function context(): AttributeContext
    {
        $value = $this->validated('context');

        return $value === null ? AttributeContext::Opportunity : AttributeContext::from($value);
    }

    public function formMode(): FormMode
    {
        $value = $this->validated('form_mode');

        return $value === null ? FormMode::Create : FormMode::from($value);
    }
}
