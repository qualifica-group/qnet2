<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Models\FinancialAccount;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /purchase-invoice-installments/payments (spec 0211, D-8): one payment
 * date and one output account for several installments of the same supplier.
 * Per-row amount and state checks run in PurchaseInvoiceBulkPayer, under the
 * document locks.
 */
class PurchaseInvoiceBulkPaymentRequest extends FormRequest
{
    public const int MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can('purchase-invoices.pay') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'financial_account_id' => ['nullable', 'integer', Rule::exists(FinancialAccount::class, 'id')],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.installment_id' => ['required', 'integer', 'distinct', Rule::exists(PurchaseInvoiceInstallment::class, 'id')],
            'items.*.paid_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, array{installment_id: int, paid_amount: string}>
     */
    public function items(): array
    {
        return array_map(fn (array $item): array => [
            'installment_id' => (int) $item['installment_id'],
            'paid_amount' => (string) $item['paid_amount'],
        ], $this->validated('items'));
    }

    public function paidAt(): string
    {
        return (string) $this->validated('paid_at');
    }

    public function financialAccountId(): ?int
    {
        $id = $this->validated('financial_account_id');

        return $id === null ? null : (int) $id;
    }
}
