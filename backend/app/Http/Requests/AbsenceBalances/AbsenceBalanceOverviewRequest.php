<?php

namespace App\Http\Requests\AbsenceBalances;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/absence-balances/overview (spec 0304 D-3): HR office only.
 */
class AbsenceBalanceOverviewRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    private const int MAX_SEARCH = 100;

    public function authorize(): bool
    {
        return $this->user()->can('absence-requests.manageAll');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'business_function_id' => ['nullable', 'integer', 'exists:business_functions,id'],
            'operational_site_id' => ['nullable', 'integer', 'exists:operational_sites,id'],
            'search' => ['nullable', 'string', 'max:'.self::MAX_SEARCH],
        ];
    }

    public function year(): int
    {
        return $this->integer('year', (int) today()->year);
    }

    /**
     * @return array{business_function_id: int|null, operational_site_id: int|null, search: string|null}
     */
    public function filters(): array
    {
        return [
            'business_function_id' => $this->filled('business_function_id') ? $this->integer('business_function_id') : null,
            'operational_site_id' => $this->filled('operational_site_id') ? $this->integer('operational_site_id') : null,
            'search' => $this->filled('search') ? (string) $this->input('search') : null,
        ];
    }
}
