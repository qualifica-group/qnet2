<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\CategoryUsageArea;
use App\Models\CompanySite;
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
    protected const array STANDALONE_REFERENCE_KEYS = ['registry_id', 'company_id', 'company_site_id', 'operational_site_id', 'product_lines'];

    /**
     * @param  bool  $required  create: client, company and lines mandatory; update: each `sometimes`
     * @return array<string, array<int, mixed>>
     */
    protected function standaloneReferenceRules(bool $required): array
    {
        // On update a present key may still not be null.
        $presence = $required ? [] : ['sometimes'];

        return [
            'registry_id' => [...$presence, 'required', 'integer', Rule::exists('registries', 'id')],
            'company_id' => [...$presence, 'required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            ...$this->productLinesRules($required, CategoryUsageArea::Opportunities),
        ];
    }

    /**
     * Cross-field rules: the lines' cross-row invariants, and the company site
     * belonging to the company, each the submitted value or else the current
     * one (an update may change either alone).
     */
    protected function validateStandaloneReferences(Validator $validator, ?int $currentCompanyId = null, ?int $currentCompanySiteId = null): void
    {
        $this->validateProductLines($validator);

        $siteId = $this->has('company_site_id') ? $this->input('company_site_id') : $currentCompanySiteId;
        $companyId = $this->has('company_id') ? $this->input('company_id') : $currentCompanyId;

        if ($siteId === null || $companyId === null || $validator->errors()->hasAny(['company_id', 'company_site_id'])) {
            return;
        }

        if (! CompanySite::query()->whereKey($siteId)->where('company_id', $companyId)->exists()) {
            $validator->errors()->add('company_site_id', __('work_orders.company_site_not_of_company'));
        }
    }
}
