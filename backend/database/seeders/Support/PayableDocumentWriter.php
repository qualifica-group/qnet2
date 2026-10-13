<?php

namespace Database\Seeders\Support;

use App\Enums\FinancialAccountType;
use App\Enums\PurchaseInvoiceType;
use App\Enums\ResidualMode;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoiceInstallmentPlanner;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The payable-document write path the seeders share (spec 0211): a document
 * billing purchase orders of one supplier and company, its schedule from the
 * real planner and the payments through the real payment service.
 */
final class PayableDocumentWriter
{
    public const string PAY_FIRST = 'first';

    public const string PAY_PARTIAL = 'partial';

    public const string PAY_NONE = 'none';

    public function __construct(
        private readonly PurchaseInvoiceInstallmentPlanner $planner,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    /**
     * @param  Collection<int, PurchaseOrder>  $orders  same supplier and company
     */
    public function document(PurchaseInvoiceType $type, string $number, Collection $orders, int $daysAgo, int $methodId, ?int $costTypeId, User $actor): PurchaseInvoice
    {
        $first = $orders->first();
        $bank = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)
            ->where('company_id', $first->company_id)->orderBy('id')->first();

        $invoice = PurchaseInvoice::query()->create([
            'document_type' => $type,
            'company_id' => $first->company_id,
            'supplier_id' => $first->supplier_id,
            'document_number' => $number,
            'document_date' => CarbonImmutable::today()->subDays($daysAgo)->toDateString(),
            'payment_method_id' => $methodId,
            'financial_account_id' => $bank?->id,
            'cost_type_id' => $costTypeId,
            'company_site_id' => $first->company_site_id,
            'net_amount' => (string) $orders->sum('taxable_total'),
            'vat_amount' => (string) $orders->sum('vat_total'),
            'total_amount' => (string) $orders->sum('grand_total'),
            'notes' => null,
            'created_by' => $actor->id,
        ]);
        $invoice->purchaseOrders()->sync($orders->pluck('id')->all());
        $this->planner->generate($invoice);

        return $invoice;
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
