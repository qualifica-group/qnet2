<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\CashFlowFilters;
use App\DataObjects\ManagementControl\ExposureFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/cash-flow (spec 0270). Authorization runs before the
 * validation: `management-control.view`.
 */
class CashFlowRequest extends FormRequest
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
            'company_id' => ['sometimes', 'array'],
            'company_id.*' => ['integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'array'],
            'company_site_id.*' => ['integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'array'],
            'operational_site_id.*' => ['integer', Rule::exists('operational_sites', 'id')],
            'document_type' => ['sometimes', Rule::in(ExposureFilters::DOCUMENT_TYPES)],
            'horizon_months' => ['sometimes', 'integer', 'min:1', 'max:'.CashFlowFilters::HORIZON_MAX],
        ];
    }

    public function filters(): CashFlowFilters
    {
        $data = $this->safe();

        return new CashFlowFilters(
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
            documentType: (string) ($data['document_type'] ?? ExposureFilters::TYPE_ALL),
            horizonMonths: (int) ($data['horizon_months'] ?? CashFlowFilters::HORIZON_DEFAULT),
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
