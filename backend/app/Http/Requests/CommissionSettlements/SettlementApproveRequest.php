<?php

namespace App\Http\Requests\CommissionSettlements;

use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/commission-settlements/{commissionSettlement}/approve
 * (spec 0267, D-4): the choices of the approval window. Approving creates a
 * payable invoice, so it needs `commission-settlements.approve` AND
 * `purchase-invoices.create`. The payment method and the cost type must be
 * active, as the purchase invoice form requires.
 */
class SettlementApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('approve', $this->route('commissionSettlement')) && $user->can('create', PurchaseInvoice::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            'cost_type_id' => ['required', 'integer', Rule::exists('cost_types', 'id')->where('is_active', true)],
            'vat_rate_id' => ['required', 'integer', Rule::exists('vat_rates', 'id')],
            'financial_account_id' => ['nullable', 'integer', Rule::exists('financial_accounts', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function choices(): array
    {
        return $this->safe()->all();
    }
}
