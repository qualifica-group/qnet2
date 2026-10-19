<?php

namespace App\Http\Requests\AbsenceBalances;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/absence-allowances (spec 0278 D-1): HR office only. The type must
 * be one that deducts a balance.
 */
class UpsertAbsenceAllowanceRequest extends FormRequest
{
    public const int MAX_MINUTES = 525600;

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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'absence_type_id' => ['required', 'integer', Rule::exists('absence_types', 'id')->where('deducts_balance', true)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_minutes' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MINUTES],
            'carried_over_minutes' => ['required', 'integer', 'min:-'.self::MAX_MINUTES, 'max:'.self::MAX_MINUTES],
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
