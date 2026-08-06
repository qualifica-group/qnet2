<?php

namespace App\Http\Requests\ProductCategories;

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for
 * GET /api/product-categories/{productCategory}/attribute-layouts (spec
 * 0062, data_contract): `context` is REQUIRED (same as the sibling
 * effective-attributes endpoint — the former implicit Opportunity default
 * died with that context, spec 0084). `form_mode` means two
 * different things on the two callers, so it is validated against two
 * different enums: an authoring load (`exact`) addresses a
 * LayoutFormScope — including the shared `all` — while a consuming load
 * addresses the concrete FormMode being rendered, for which `all` is not a
 * valid value. Authorization is intentionally NOT handled here (it stays in
 * the controller via authorize('view', $productCategory)).
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
            'context' => ['required', Rule::enum(AttributeContext::class)],
            'form_mode' => [
                'sometimes',
                $this->boolean('exact') ? Rule::enum(LayoutFormScope::class) : Rule::enum(FormMode::class),
            ],
            'exact' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The configurator's authoring load sets this to request the RAW row for
     * the exact scope (no shared-layout inheritance); consumers (product
     * form) leave it off and get the shared-layout fallback resolution.
     */
    public function exact(): bool
    {
        return $this->boolean('exact');
    }

    /** The authored scope: the shared layout unless a specific mode is asked for. */
    public function scope(): LayoutFormScope
    {
        $value = $this->validated('form_mode');

        return $value === null ? LayoutFormScope::All : LayoutFormScope::from($value);
    }

    public function context(): AttributeContext
    {
        return AttributeContext::from($this->validated('context'));
    }

    public function formMode(): FormMode
    {
        $value = $this->validated('form_mode');

        return $value === null ? FormMode::Create : FormMode::from($value);
    }
}
