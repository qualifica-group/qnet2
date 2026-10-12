<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Enums\PurchaseInvoiceType;
use App\Enums\ResidualMode;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoiceInstallmentPlanner;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seed fake payable documents (spec 0211): over the demo purchase orders, one
 * document billing two orders of the same supplier, one proforma billing the
 * rest of an order and one invoice billing the same order again (partial
 * billing, N:N). The schedules come from the planner and some installments are
 * paid (fully and partially) through the payment service. Idempotent: each
 * document is keyed by its demo number. Skipped quietly when the data it hangs
 * on (purchase orders, a user, payment methods) is missing; depends on
 * DemoPurchaseOrderSeeder, DemoPaymentMethodSeeder and DemoFinancialAccountSeeder.
 */
class DemoPurchaseInvoiceSeeder extends Seeder
{
    /**
     * One scenario per document: the orders it bills (by position among the
     * demo orders, grouped by supplier), the age of the document and what is paid.
     *
     * @var array<int, array{number: string, type: PurchaseInvoiceType, orders: array<int, int>, days_ago: int, method: string, pay: string}>
     */
    private const array SCENARIOS = [
        ['number' => 'DEMO-PI-001', 'type' => PurchaseInvoiceType::Invoice, 'orders' => [0, 1], 'days_ago' => 120, 'method' => 'bank_transfer_60_90_120', 'pay' => 'first'],
        ['number' => 'DEMO-PI-002', 'type' => PurchaseInvoiceType::Proforma, 'orders' => [2], 'days_ago' => 60, 'method' => 'riba_30_60', 'pay' => 'partial'],
        ['number' => 'DEMO-PI-003', 'type' => PurchaseInvoiceType::Invoice, 'orders' => [2], 'days_ago' => 20, 'method' => 'bank_transfer', 'pay' => 'none'],
    ];

    public function __construct(
        private readonly PurchaseInvoiceInstallmentPlanner $planner,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    public function run(): void
    {
        // Step 1: resolve the shared context; nothing to bill without it
        $actor = User::query()->orderBy('id')->first();
        $orders = PurchaseOrder::query()->where('grand_total', '>', 0)->orderBy('id')->get()->values();
        $methods = PaymentMethod::query()->pluck('id', 'code');
        $costTypeId = CostType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->value('id');

        if ($actor === null || $orders->isEmpty() || $methods->isEmpty()) {
            return;
        }

        // Step 2: one document per scenario whose orders exist and share supplier and company
        foreach (self::SCENARIOS as $scenario) {
            $documentOrders = collect($scenario['orders'])->map(fn (int $position): ?PurchaseOrder => $orders->get($position))->filter()->values();

            if ($documentOrders->count() !== count($scenario['orders']) || ! $this->sameSupplierAndCompany($documentOrders)) {
                continue;
            }

            if (PurchaseInvoice::query()->where('document_number', $scenario['number'])->exists()) {
                continue;
            }

            $methodId = $methods->get($scenario['method']) ?? $methods->first();
            $invoice = $this->document($scenario, $documentOrders, $methodId, $costTypeId, $actor);

            // Step 3: the schedule, then the payments
            $this->planner->generate($invoice);
            $this->pay($invoice, $scenario['pay']);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private function sameSupplierAndCompany(Collection $orders): bool
    {
        return $orders->pluck('supplier_id')->unique()->count() === 1 && $orders->pluck('company_id')->unique()->count() === 1;
    }

    /**
     * @param  array{number: string, type: PurchaseInvoiceType, orders: array<int, int>, days_ago: int, method: string, pay: string}  $scenario
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private function document(array $scenario, Collection $orders, int $methodId, ?int $costTypeId, User $actor): PurchaseInvoice
    {
        $first = $orders->first();
        $bank = FinancialAccount::query()->where('type', FinancialAccountType::BankAccount->value)
            ->where('company_id', $first->company_id)->orderBy('id')->first();

        $invoice = PurchaseInvoice::query()->create([
            'document_type' => $scenario['type'],
            'company_id' => $first->company_id,
            'supplier_id' => $first->supplier_id,
            'document_number' => $scenario['number'],
            'document_date' => CarbonImmutable::today()->subDays($scenario['days_ago'])->toDateString(),
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

        return $invoice;
    }

    private function pay(PurchaseInvoice $invoice, string $mode): void
    {
        $installment = $invoice->installments()->first();

        if ($mode === 'none' || $installment === null) {
            return;
        }

        $payload = [
            'paid_amount' => (string) $installment->amount,
            'paid_at' => $installment->due_date->min(CarbonImmutable::today())->toDateString(),
            'financial_account_id' => $invoice->financial_account_id,
        ];

        // A partial payment moves its residual on a new installment (spec 0211, D-6).
        if ($mode === 'partial') {
            $payload['paid_amount'] = bcdiv((string) $installment->amount, '2', 2);
            $payload['residual_mode'] = ResidualMode::NewInstallment->value;
            $payload['residual_due_date'] = $installment->due_date->addDays(30)->toDateString();
        }

        $this->payments->record($installment, $payload);
    }
}
