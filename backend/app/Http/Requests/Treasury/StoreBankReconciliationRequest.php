<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/financial-accounts/{financialAccount}/bank-reconciliations (spec 0363).
 * `treasury-movements.reconcile`, plus `treasury-movements.create` when it also creates the adjustment (D-4).
 */
class StoreBankReconciliationRequest extends FormRequest
{
    private const string BALANCE_MAX = '999999999999.99';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('treasury-movements.reconcile') === true
            && (! $this->boolean('create_adjustment') || $user->can('treasury-movements.create'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'statement_date' => ['required', 'date_format:Y-m-d'],
            'bank_balance' => ['required', 'numeric', 'decimal:0,2', 'between:-'.self::BALANCE_MAX.','.self::BALANCE_MAX],
            'note' => ['nullable', 'string', 'max:255'],
            'create_adjustment' => ['required', 'boolean'],
            'treasury_type_id' => ['nullable', 'integer', Rule::exists('treasury_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['treasury_type_id.exists' => __('treasury_reconciliations.adjustment_type_invalid')];
    }
}
