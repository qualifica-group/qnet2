<?php

declare(strict_types=1);

namespace App\Http\Requests\ManagementControl;

use App\DataObjects\ManagementControl\AgentsFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /management-control/agents (spec 0272). Authorization runs before the
 * validation: `management-control.view`.
 */
class AgentsRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', $this->withinMaxMonths()],
            'company_id' => ['sometimes', 'array'],
            'company_id.*' => ['integer', Rule::exists('companies', 'id')],
            'top' => ['sometimes', 'integer', 'min:1', 'max:'.AgentsFilters::TOP_MAX],
        ];
    }

    public function filters(): AgentsFilters
    {
        $data = $this->safe();
        $companyIds = array_values(array_unique(array_map('intval', $data['company_id'] ?? [])));
        sort($companyIds);

        return new AgentsFilters(
            dateFrom: $data['date_from'] ?? null,
            dateTo: $data['date_to'] ?? null,
            companyIds: $companyIds,
            top: (int) ($data['top'] ?? AgentsFilters::TOP_DEFAULT),
        );
    }

    /** The interval spans at most MAX_MONTHS calendar months; a malformed or missing date is reported by its own rule. */
    private function withinMaxMonths(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->input('date_from');
            if (! is_string($from) || ! is_string($value)
                || preg_match(self::DATE_PATTERN, $from) !== 1 || preg_match(self::DATE_PATTERN, $value) !== 1) {
                return;
            }

            if (count(AgentsFilters::monthsBetween($from, $value)) > AgentsFilters::MAX_MONTHS) {
                $fail('The interval cannot exceed '.AgentsFilters::MAX_MONTHS.' months.');
            }
        };
    }
}
