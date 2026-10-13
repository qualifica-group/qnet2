<?php

namespace Database\Seeders\Support;

use App\Enums\FinancialAccountType;
use App\Enums\PurchaseInvoiceType;
use App\Enums\ResidualMode;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use App\Services\PurchaseInvoices\PurchaseInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The payable-document write path the seeders share (spec 0211, 0250): a document
 * billing purchase orders of one supplier and company, written through the real
 * document service (lines, amounts, schedule) and the payments through the real
 * payment service.
 */
final class PayableDocumentWriter
{
    public const string PAY_FIRST = 'first';

    public const string PAY_PARTIAL = 'partial';

    public const string PAY_NONE = 'none';

    public function __construct(
        private readonly PurchaseInvoiceService $service,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    /**
     * The document is written through the real service (spec 0250), so it gets
     * its lines, amounts and schedule exactly as the application does. Each line
     * is linked to its purchase order line (spec 0252, D-4), so the orders must
     * be invoiceable and no order may be billed by two documents: the work order
     * actuals count an order line once, through the document that bills it.
     *
     * @param  Collection<int, PurchaseOrder>  $orders  same supplier and company
     */
    public function document(PurchaseInvoiceType $type, string $number, Collection $orders, int $daysAgo, int $methodId, ?int $costTypeId, User $actor): PurchaseInvoice
    {
        $first = $orders->first();
        $bank = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)
            ->where('company_id', $first->company_id)->orderBy('id')->first();

        $invoice = $this->service->create($actor, [
            'document_type' => $type->value,
            'document_number' => $number,
            'document_date' => CarbonImmutable::today()->subDays($daysAgo)->toDateString(),
            'company_id' => $first->company_id,
            'supplier_id' => $first->supplier_id,
            'payment_method_id' => $methodId,
            'financial_account_id' => $bank?->id,
            'cost_type_id' => $costTypeId,
            'company_site_id' => $first->company_site_id,
            'lines' => $this->lines($orders, $number, $costTypeId),
        ]);
        $invoice->purchaseOrders()->sync($orders->pluck('id')->all());

        return $invoice;
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function lines(Collection $orders, string $number, ?int $costTypeId): array
    {
        $lines = PurchaseOrderLine::query()->whereIn('purchase_order_id', $orders->pluck('id')->all())
            ->orderBy('purchase_order_id')->orderBy('position')->orderBy('id')->get()
            ->map(fn (PurchaseOrderLine $line): array => [
                'purchase_order_line_id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'cost_type_id' => $line->cost_type_id,
                'work_order_id' => $line->work_order_id,
            ])->values()->all();

        if ($lines !== []) {
            return $lines;
        }

        return [[
            'description' => "Documento {$number}",
            'quantity' => '1',
            'unit_price' => (string) $orders->sum('taxable_total'),
            'cost_type_id' => $costTypeId,
        ]];
    }

    public function pay(PurchaseInvoice $invoice, string $mode): void
    {
        $installment = $invoice->installments()->first();

        if ($mode === self::PAY_NONE || $installment === null) {
            return;
        }

        $payload = [
            'paid_amount' => (string) $installment->amount,
            'paid_at' => $installment->due_date->min(CarbonImmutable::today())->toDateString(),
            'financial_account_id' => $invoice->financial_account_id,
        ];

        // A partial payment moves its residual on a new installment (spec 0211, D-6).
        if ($mode === self::PAY_PARTIAL) {
            $payload['paid_amount'] = bcdiv((string) $installment->amount, '2', 2);
            $payload['residual_mode'] = ResidualMode::NewInstallment->value;
            $payload['residual_due_date'] = $installment->due_date->addDays(30)->toDateString();
        }

        $this->payments->record($installment, $payload);
    }
}
