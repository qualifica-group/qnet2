<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\ExposureFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/exposure (spec 0269). Authorization runs before the
 * validation: `management-control.view`.
 */
class ExposureRequest extends FormRequest
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
            'document_type' => ['sometimes', Rule::in(ExposureFilters::DOCUMENT_TYPES)],
            'group_by' => ['sometimes', Rule::in(ExposureFilters::GROUP_BY_OPTIONS)],
            'top' => ['sometimes', 'integer', 'min:1', 'max:'.ExposureFilters::TOP_MAX],
        ];
    }

    public function filters(): ExposureFilters
    {
        $data = $this->safe();

        return new ExposureFilters(
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
            documentType: (string) ($data['document_type'] ?? ExposureFilters::TYPE_INVOICE),
            groupBy: (string) ($data['group_by'] ?? ExposureFilters::GROUP_COMPANY),
            top: (int) ($data['top'] ?? ExposureFilters::TOP_DEFAULT),
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
