<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\MonthlyTrendFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/monthly-trend (spec 0270). Authorization runs before
 * the validation: `management-control.view`.
 */
class MonthlyTrendRequest extends FormRequest
{
    private const string DATE_PATTERN = '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';

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
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', $this->withinMaxMonths()],
            'company_id' => ['sometimes', 'array'],
            'company_id.*' => ['integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['sometimes', 'array'],
            'company_site_id.*' => ['integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => ['sometimes', 'array'],
            'operational_site_id.*' => ['integer', Rule::exists('operational_sites', 'id')],
        ];
    }

    public function filters(): MonthlyTrendFilters
    {
        $data = $this->safe();

        return new MonthlyTrendFilters(
            dateFrom: (string) $data['date_from'],
            dateTo: (string) $data['date_to'],
            companyIds: $this->ids($data['company_id'] ?? []),
            companySiteIds: $this->ids($data['company_site_id'] ?? []),
            operationalSiteIds: $this->ids($data['operational_site_id'] ?? []),
        );
    }

    /** The interval spans at most MAX_MONTHS calendar months; a malformed date is reported by its own rule. */
    private function withinMaxMonths(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->input('date_from');
            if (! is_string($from) || ! is_string($value)
                || preg_match(self::DATE_PATTERN, $from) !== 1 || preg_match(self::DATE_PATTERN, $value) !== 1) {
                return;
            }

            if (count((new MonthlyTrendFilters($from, $value))->months()) > MonthlyTrendFilters::MAX_MONTHS) {
                $fail('The interval cannot exceed '.MonthlyTrendFilters::MAX_MONTHS.' months.');
            }
        };
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
