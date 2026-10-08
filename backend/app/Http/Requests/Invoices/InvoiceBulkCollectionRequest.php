<?php

namespace App\Http\Requests\Invoices;

use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /invoice-installments/collections (spec 0198): one collection date for
 * several installments of the same customer. Per-row amount and state checks
 * run in InvoiceBulkCollector, under the invoice locks.
 */
class InvoiceBulkCollectionRequest extends FormRequest
{
    public const int MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return $this->user()?->can('invoices.collect') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'collected_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.installment_id' => ['required', 'integer', 'distinct', Rule::exists(InvoiceInstallment::class, 'id')],
            'items.*.collected_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, array{installment_id: int, collected_amount: string}>
     */
    public function items(): array
    {
        return array_map(fn (array $item): array => [
            'installment_id' => (int) $item['installment_id'],
            'collected_amount' => (string) $item['collected_amount'],
        ], $this->validated('items'));
    }

    public function collectedAt(): string
    {
        return (string) $this->validated('collected_at');
    }
}
