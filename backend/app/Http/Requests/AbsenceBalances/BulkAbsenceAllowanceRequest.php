<?php

namespace App\Http\Requests\AbsenceBalances;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/absence-allowances/bulk (spec 0304 D-12): HR office only. A null
 * `annual_days` returns the people to the default of the type.
 */
class BulkAbsenceAllowanceRequest extends FormRequest
{
    public const int MAX_USERS = 500;

    public const int MAX_DAYS = 366;

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
            'absence_type_id' => ['required', 'integer', Rule::exists('absence_types', 'id')->where('deducts_balance', true)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'user_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_USERS],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'annual_days' => ['present', 'nullable', 'numeric', 'min:0', 'max:'.self::MAX_DAYS, 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['absence_type_id.exists' => __('absence_balances.type_not_deducting')];
    }
}
