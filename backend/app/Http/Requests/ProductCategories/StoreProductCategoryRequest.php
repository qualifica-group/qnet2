<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
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
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'inherits_product_attributes' => ['sometimes', 'boolean'],
            // Spec 0084: the Offerta usage context's own inheritance barrier.
            'inherits_quote_attributes' => ['sometimes', 'boolean'],
            // Spec 0098: the Commessa usage context's own inheritance barrier.
            'inherits_work_order_attributes' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
            // Only meaningful on a ROOT category: under a parent the value is
            // inherited, and ProductCategoryService refuses a divergent one.
            'requires_quote' => ['sometimes', 'boolean'],
            // Spec 0074: whether the category may be picked as a
            // classification target. Omitted = true (selectable).
            'is_selectable' => ['sometimes', 'boolean'],
            // Spec 0131 (user directive 2026-09-18): the node's own override of
            // the inherited report flag. Omitted or null = inherit from the parent.
            'is_reportable' => ['sometimes', 'nullable', 'boolean'],
            // Spec 0141: the node's own report column selection — an allow-list
            // of the indicator catalog (backend.md §8: never raw input).
            // Omitted, null or [] = inherit from the parent (normalized by
            // ProductCategoryService). Duplicates 422 (AC-002).
            'report_columns' => ['sometimes', 'nullable', 'array'],
            'report_columns.*' => ['string', 'distinct', Rule::in((array) config('request-management-report.indicator_columns'))],
            // Spec 0077: same root-only semantics as requires_quote — omitted
            // = server-resolved (inherited, or "multiple" at a fresh root).
            'management_mode' => ['sometimes', Rule::enum(CategoryManagementMode::class)],
            // User directive 2026-08-07: same root-only semantics again —
            // omitted = server-resolved (inherited, or false at a fresh root).
            'single_quote_per_opportunity' => ['sometimes', 'boolean'],
            // Spec 0091: same root-only semantics once more — omitted =
            // server-resolved (inherited, or true at a fresh root).
            'generates_contract' => ['sometimes', 'boolean'],
            // Spec 0114: same root-only semantics again — omitted =
            // server-resolved (inherited, or false at a fresh root).
            'simplified_offer_line' => ['sometimes', 'boolean'],
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
            // Row action "duplicate": the category whose own attribute layouts
            // are copied onto the new one. Viewing it is authorized in the
            // controller; the layouts are re-validated against the copy.
            'layout_source_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
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
        return null;
    }

    /**
     * The category the duplicate row action copies the attribute layouts
     * from, or null on a plain create.
     */
    public function layoutSource(): ?ProductCategory
    {
        $sourceId = $this->validated('layout_source_id');

        return $sourceId === null ? null : ProductCategory::query()->findOrFail((int) $sourceId);
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
