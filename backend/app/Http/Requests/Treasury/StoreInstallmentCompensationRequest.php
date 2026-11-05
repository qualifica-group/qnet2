<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use App\Enums\ResidualMode;
use App\Http\Requests\Concerns\DeniesWithMessage;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /installment-compensations (spec 0364): needs the collect ability on
 * the active invoices and the pay ability on the purchase ones. Authorization
 * runs before the validation, so a forbidden user never sees a 422. The checks
 * that need the locked rows (same registry/company, open) live in the service.
 */
class StoreInstallmentCompensationRequest extends FormRequest
{
    use DeniesWithMessage;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && $actor->can('invoices.collect') && $actor->can('purchase-invoices.pay');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'invoice_installment_id' => ['required', 'integer', Rule::exists(InvoiceInstallment::class, 'id')],
            'purchase_invoice_installment_id' => ['required', 'integer', Rule::exists(PurchaseInvoiceInstallment::class, 'id')],
            'compensated_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'residual_mode' => ['nullable', Rule::in([ResidualMode::Spread->value, ResidualMode::NewInstallment->value])],
            'residual_due_date' => [
                Rule::requiredIf(fn (): bool => $this->input('residual_mode') === ResidualMode::NewInstallment->value),
                'nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function deniedMessage(): string
    {
        return __('treasury_compensations.registered_forbidden');
    }
}
