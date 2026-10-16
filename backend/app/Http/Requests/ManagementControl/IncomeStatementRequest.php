<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\IncomeStatementFilters;
use App\Enums\WorkOrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/income-statement (spec 0268). Authorization runs
 * before the validation: `management-control.view`.
 */
class IncomeStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management-control.view') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'company_id' => ['sometimes', 'array'],
            'company_id.*' => ['integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'array'],
            'company_site_id.*' => ['integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'array'],
            'operational_site_id.*' => ['integer', Rule::exists('operational_sites', 'id')],
            'business_function_id' => ['sometimes', 'array'],
            'business_function_id.*' => ['integer', Rule::exists('business_functions', 'id')],
            'work_order_type' => ['sometimes', 'array'],
            'work_order_type.*' => [Rule::in(WorkOrderType::values())],
            'commercial_id' => ['sometimes', 'array'],
            'commercial_id.*' => ['integer', Rule::exists('referents', 'id')],
            'revenue_basis' => ['sometimes', Rule::in(IncomeStatementFilters::REVENUE_BASES)],
            'segment_by' => ['sometimes', Rule::in(IncomeStatementFilters::SEGMENT_BY_OPTIONS)],
        ];
    }

    public function filters(): IncomeStatementFilters
    {
        $data = $this->safe();

        return new IncomeStatementFilters(
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
            businessFunctionIds: $this->ids($data['business_function_id'] ?? []),
            workOrderTypes: $this->strings($data['work_order_type'] ?? []),
            commercialIds: $this->ids($data['commercial_id'] ?? []),
            revenueBasis: (string) ($data['revenue_basis'] ?? IncomeStatementFilters::BASIS_CONTRACT),
            segmentBy: (string) ($data['segment_by'] ?? IncomeStatementFilters::SEGMENT_NONE),
        );
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function ids(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function strings(array $values): array
    {
        $values = array_values(array_unique(array_map('strval', $values)));
        sort($values);

        return $values;
    }
}
