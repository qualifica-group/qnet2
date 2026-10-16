<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\StructureCostsFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/structure-costs (spec 0268). Authorization runs
 * before the validation: `management-control.view`.
 */
class StructureCostsRequest extends FormRequest
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
            'group_by' => ['sometimes', Rule::in(StructureCostsFilters::GROUP_BY_OPTIONS)],
        ];
    }

    public function filters(): StructureCostsFilters
    {
        $data = $this->safe();

        return new StructureCostsFilters(
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
            groupBy: (string) ($data['group_by'] ?? StructureCostsFilters::GROUP_COMPANY),
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
}
