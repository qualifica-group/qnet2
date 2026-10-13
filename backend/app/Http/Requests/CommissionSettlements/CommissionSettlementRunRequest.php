<?php

namespace App\Http\Requests\CommissionSettlements;

use App\Services\Commissions\Settlements\SettlementRunData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the body of POST /api/commission-settlement-runs (spec 0213, D-6).
 * Authorization stays in the controller (CommissionSettlementPolicy::create).
 */
class CommissionSettlementRunRequest extends FormRequest
{
    /** The agent roles a run can be narrowed to: SUPPLIER is not an agent commission. */
    private const array FILTERABLE_ROLES = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR'];

    private const string AMOUNT_MAX = '9999999999999.99';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'referent_ids' => ['nullable', 'array'],
            'referent_ids.*' => ['integer', 'distinct', Rule::exists('referents', 'id')],
            'recipient_roles' => ['nullable', 'array'],
            'recipient_roles.*' => ['string', 'distinct', Rule::in(self::FILTERABLE_ROLES)],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:'.self::AMOUNT_MAX, 'decimal:0,2'],
        ];
    }

    public function toData(): SettlementRunData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->safe()->all();

        return SettlementRunData::fromValidated($validated);
    }
}
