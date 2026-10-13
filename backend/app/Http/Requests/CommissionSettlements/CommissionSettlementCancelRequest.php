<?php

namespace App\Http\Requests\CommissionSettlements;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/commission-settlements/{commissionSettlement}/cancel
 * (spec 0213, D-5): the reason is mandatory.
 */
class CommissionSettlementCancelRequest extends FormRequest
{
    private const int REASON_MAX = 500;

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
            'reason' => ['required', 'string', 'max:'.self::REASON_MAX],
        ];
    }

    public function reason(): string
    {
        return (string) $this->safe()->input('reason');
    }
}
