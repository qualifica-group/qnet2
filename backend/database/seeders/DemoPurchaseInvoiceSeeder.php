<?php

namespace Database\Seeders;

use App\Enums\PurchaseInvoiceType;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Database\Seeders\Support\PayableDocumentWriter as Writer;
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
        ['number' => 'DEMO-PI-001', 'type' => PurchaseInvoiceType::Invoice, 'orders' => [0, 1], 'days_ago' => 120, 'method' => 'bank_transfer_60_90_120', 'pay' => Writer::PAY_FIRST],
        ['number' => 'DEMO-PI-002', 'type' => PurchaseInvoiceType::Proforma, 'orders' => [2], 'days_ago' => 60, 'method' => 'riba_30_60', 'pay' => Writer::PAY_PARTIAL],
        ['number' => 'DEMO-PI-003', 'type' => PurchaseInvoiceType::Invoice, 'orders' => [2], 'days_ago' => 20, 'method' => 'bank_transfer', 'pay' => Writer::PAY_NONE],
    ];

    public function __construct(private readonly Writer $writer) {}

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

            // Step 3: the document with its schedule, then the payments
            $methodId = $methods->get($scenario['method']) ?? $methods->first();
            $invoice = $this->writer->document($scenario['type'], $scenario['number'], $documentOrders, $scenario['days_ago'], $methodId, $costTypeId, $actor);
            $this->writer->pay($invoice, $scenario['pay']);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private function sameSupplierAndCompany(Collection $orders): bool
    {
        return $orders->pluck('supplier_id')->unique()->count() === 1 && $orders->pluck('company_id')->unique()->count() === 1;
    }
}
