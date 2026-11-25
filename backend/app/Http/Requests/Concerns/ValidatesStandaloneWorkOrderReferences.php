<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\CategoryUsageArea;
use App\Models\CompanySite;
use App\Rules\ActiveProduct;
use App\Services\Opportunities\ProductCategoryCoherence;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The own client, company, sites and business-function lines of a project
 * commessa born without an offer (spec 0431, D-8), shared by its create
 * (StoreStandaloneProjectWorkOrderRequest) and its update
 * (UpdateWorkOrderRequest): one set of rules for both.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesStandaloneWorkOrderReferences
{
    use ValidatesProductLines;

    /** The keys only a commessa born without an offer may carry. */
    protected const array STANDALONE_REFERENCE_KEYS = ['registry_id', 'company_id', 'company_site_id', 'operational_site_id', 'product_lines', 'product_ids'];

    /**
     * @param  bool  $required  create: client, company and lines mandatory; update: each `sometimes`
     * @param  array<int, int>  $currentProductIds  the products already on the work order (an inactive one may stay)
     * @return array<string, array<int, mixed>>
     */
    protected function standaloneReferenceRules(bool $required, array $currentProductIds = []): array
    {
        // On update a present key may still not be null.
        $presence = $required ? [] : ['sometimes'];

        return [
            'registry_id' => [...$presence, 'required', 'integer', Rule::exists('registries', 'id')],
            'company_id' => [...$presence, 'required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            ...$this->productLinesRules($required, CategoryUsageArea::Opportunities),
            // D-17: optional products, in place of the offer lines; their categories bring the flexible fields.
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'distinct', new ActiveProduct($currentProductIds)],
        ];
    }

    /**
     * Cross-field rules: the lines' cross-row invariants, the company site
     * belonging to the company, and every product belonging to one of the
     * product lines (D-18) — each the submitted value or else the current
     * one (an update may change either alone).
     *
     * @param  array<int, int>  $currentProductIds
     * @param  array<int, int>  $currentCategoryIds  the categories of the product lines already saved
     */
    protected function validateStandaloneReferences(
        Validator $validator,
        ?int $currentCompanyId = null,
        ?int $currentCompanySiteId = null,
        array $currentProductIds = [],
        array $currentCategoryIds = [],
    ): void {
        $this->validateProductLines($validator);
        $this->validateProductsCoherence($validator, $currentProductIds, $currentCategoryIds);

        $siteId = $this->has('company_site_id') ? $this->input('company_site_id') : $currentCompanySiteId;
        $companyId = $this->has('company_id') ? $this->input('company_id') : $currentCompanyId;

        if ($siteId === null || $companyId === null || $validator->errors()->hasAny(['company_id', 'company_site_id'])) {
            return;
        }

        if (! CompanySite::query()->whereKey($siteId)->where('company_id', $companyId)->exists()) {
            $validator->errors()->add('company_site_id', __('work_orders.company_site_not_of_company'));
        }
    }

    /**
     * D-18: the products of the work order are picked within its product
     * lines, as an opportunity's products of interest are (the same rule,
     * ProductCategoryCoherence). Checked only when one side is submitted.
     *
     * @param  array<int, int>  $currentProductIds
     * @param  array<int, int>  $currentCategoryIds
     */
    private function validateProductsCoherence(Validator $validator, array $currentProductIds, array $currentCategoryIds): void
    {
        if ((! $this->has('product_ids') && ! $this->has('product_lines')) || $validator->errors()->hasAny(['product_ids', 'product_ids.*', 'product_lines', 'product_lines.*'])) {
            return;
        }

        $productIds = $this->has('product_ids')
            ? array_map(static fn (mixed $id): int => (int) $id, (array) $this->input('product_ids'))
            : $currentProductIds;
        $categoryIds = $this->has('product_lines')
            ? array_map(static fn (mixed $line): int => (int) (is_array($line) ? ($line['product_category_id'] ?? 0) : 0), (array) $this->input('product_lines'))
            : $currentCategoryIds;

        $offending = app(ProductCategoryCoherence::class)->offendingProducts($productIds, $categoryIds);
        if ($offending !== []) {
            $validator->errors()->add('product_ids', app(ProductCategoryCoherence::class)->message($offending, ProductCategoryCoherence::WORK_ORDER_MESSAGE));
        }
    }
}
