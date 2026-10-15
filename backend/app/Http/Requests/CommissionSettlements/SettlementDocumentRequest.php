<?php

namespace App\Http\Requests\CommissionSettlements;

use App\Services\Commissions\Settlements\DocumentToleranceChecker;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/commission-settlements/{commissionSettlement}/document
 * (spec 0267, D-3): number, date (not in the future) and the five declared
 * amounts. Authorization (`receiveDocument`) runs before validation, so a
 * missing permission is a 403 whatever the body. Coherence (total = taxable + VAT) and the tolerance against the
 * settlement belong to SettlementDocumentService.
 */
class SettlementDocumentRequest extends FormRequest
{
    private const int NUMBER_MAX = 30;

    public function authorize(): bool
    {
        return $this->user()->can('receiveDocument', $this->route('commissionSettlement'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'document_number' => ['required', 'string', 'max:'.self::NUMBER_MAX],
            'document_date' => ['required', 'date', 'before_or_equal:today'],
        ];

        foreach (DocumentToleranceChecker::AMOUNTS as $amount) {
            $rules[DocumentToleranceChecker::FIELD_PREFIX.$amount] = ['required', 'numeric', 'min:0', 'decimal:0,2'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->safe()->all();
    }
}
