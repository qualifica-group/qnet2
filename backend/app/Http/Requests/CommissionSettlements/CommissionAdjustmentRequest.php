<?php

namespace App\Http\Requests\CommissionSettlements;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/commission-adjustments and PUT /api/commission-adjustments/{id}
 * (spec 0213, D-7). The update only takes amount, date and notes: agent, company
 * and settlement are fixed when the adjustment is created. The "same agent and
 * company, still a draft" rule of the settlement is enforced by the Service
 * under lock; here only the id is checked to exist.
 */
class CommissionAdjustmentRequest extends FormRequest
{
    private const int NOTES_MAX = 500;

    private const string AMOUNT_LIMIT = '9999999999999.99';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'amount' => [
                'bail', 'required', 'numeric', 'decimal:0,2',
                'min:-'.self::AMOUNT_LIMIT, 'max:'.self::AMOUNT_LIMIT,
                fn (string $attribute, mixed $value, Closure $fail) => bccomp((string) $value, '0', 2) === 0
                    ? $fail(__('The adjustment amount cannot be zero.'))
                    : null,
            ],
            'accrued_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['required', 'string', 'max:'.self::NOTES_MAX],
        ];

        if ($this->isMethod('POST')) {
            $rules += [
                'referent_id' => ['required', 'integer', Rule::exists('referents', 'id')],
                'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
                'commission_settlement_id' => ['nullable', 'integer', Rule::exists('commission_settlements', 'id')],
            ];
        }

        return $rules;
    }

    /**
     * @return array{referent_id?: int, company_id?: int, amount: string, accrued_on: string, notes: string, commission_settlement_id?: int|null}
     */
    public function toData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->all();
        $data = [
            'amount' => bcadd((string) $validated['amount'], '0', 2),
            'accrued_on' => (string) $validated['accrued_on'],
            'notes' => (string) $validated['notes'],
        ];

        if ($this->isMethod('POST')) {
            $data['referent_id'] = (int) $validated['referent_id'];
            $data['company_id'] = (int) $validated['company_id'];
            $data['commission_settlement_id'] = isset($validated['commission_settlement_id']) ? (int) $validated['commission_settlement_id'] : null;
        }

        return $data;
    }
}
