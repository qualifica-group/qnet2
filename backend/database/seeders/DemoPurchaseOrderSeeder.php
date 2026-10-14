<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\PurchaseOrders\PurchaseOrderReceiptService;
use App\Services\PurchaseOrders\PurchaseOrderService;
use Illuminate\Database\Seeder;

/**
 * Seed fake purchase orders (spec 0210, 0254): a draft with two lines, one
 * approved and one sent to the supplier that stay open, three received in full
 * (fulfilled: they feed the demo invoices, so no automatic receipt is needed)
 * and one partially received. Receipts go through the real service,
 * so received quantities and the derived status are the application's own.
 * Idempotent: each order is keyed by its description. Skipped quietly when the reference data it hangs on (a user, a
 * company site, a supplier registry, the cost types of the migration) is missing.
 */
class DemoPurchaseOrderSeeder extends Seeder
{
    private const string RECEIVE_ALL = 'all';

    private const string RECEIVE_PARTIAL = 'partial';

    public function run(PurchaseOrderService $orders, PurchaseOrderReceiptService $receipts): void
    {
        $actor = User::query()->orderBy('id')->first();
        $site = CompanySite::query()->whereNotNull('company_id')->orderBy('id')->first();
        $supplier = Registry::query()->where('is_supplier', true)->orderBy('id')->first();
        $costTypes = CostType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('id');

        if ($actor === null || $site === null || $supplier === null || $costTypes->isEmpty()) {
            return;
        }

        $header = [
            'supplier_id' => $supplier->id,
            'company_id' => $site->company_id,
            'company_site_id' => $site->id,
            'order_date' => now()->toDateString(),
        ];
        $vatRateId = VatRate::query()->orderByDesc('rate')->value('id');
        $line = fn (string $description, string $quantity, string $price, int $costTypeId): array => [
            'id' => null, 'product_id' => null, 'description' => $description, 'cost_type_id' => $costTypeId,
            'unit_of_measure_id' => null, 'quantity' => $quantity, 'unit_price' => $price,
            'vat_rate_id' => $vatRateId, 'work_order_id' => null,
        ];

        $this->order($orders, $receipts, $actor, 'Consulenza e canone sede (demo)', PurchaseOrderStatus::Draft, $header, [
            $line('Consulenza specialistica', '10', '85.00', $costTypes->first()),
            $line('Canone mensile sede', '1', '1200.00', $costTypes->last()),
        ]);
        $this->order($orders, $receipts, $actor, 'Utenze del trimestre (demo)', PurchaseOrderStatus::Approved, $header, [
            $line('Energia elettrica', '3', '240.00', $costTypes->last()),
        ], self::RECEIVE_ALL);
        $this->order($orders, $receipts, $actor, 'Collaborazione esterna (demo)', PurchaseOrderStatus::SentToSupplier, $header, [
            $line('Prestazione professionale', '1', '3500.00', $costTypes->first()),
        ]);
        $this->order($orders, $receipts, $actor, 'Forniture ufficio (demo)', PurchaseOrderStatus::Approved, $header, [
            $line('Cancelleria e consumabili', '5', '60.00', $costTypes->last()),
        ], self::RECEIVE_ALL);
        $this->order($orders, $receipts, $actor, 'Manutenzione impianti (demo)', PurchaseOrderStatus::SentToSupplier, $header, [
            $line('Intervento di manutenzione', '2', '450.00', $costTypes->first()),
        ], self::RECEIVE_ALL);
        $this->order($orders, $receipts, $actor, 'Attrezzature laboratorio (demo)', PurchaseOrderStatus::SentToSupplier, $header, [
            $line('Banco da lavoro', '4', '320.00', $costTypes->last()),
            $line('Set utensili', '10', '45.00', $costTypes->last()),
        ], self::RECEIVE_PARTIAL);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     * @param  ?string  $receive  RECEIVE_ALL or RECEIVE_PARTIAL; null leaves the order unreceived
     */
    private function order(PurchaseOrderService $orders, PurchaseOrderReceiptService $receipts, User $actor, string $description, PurchaseOrderStatus $status, array $header, array $lines, ?string $receive = null): void
    {
        if (PurchaseOrder::query()->where('description', $description)->exists()) {
            return;
        }

        $order = $orders->createOne($header + ['description' => $description], $lines, null, $actor);
        $order->forceFill(['status' => $status])->save();

        if ($receive !== null) {
            $this->receive($receipts, $order->load('lines'), $actor, $receive);
        }
    }

    /** Full: every line in one delivery. Partial: half of the first line only, the others stay untouched. */
    private function receive(PurchaseOrderReceiptService $receipts, PurchaseOrder $order, User $actor, string $mode): void
    {
        $lines = $order->lines->map(fn (PurchaseOrderLine $line): array => [
            'purchase_order_line_id' => $line->id,
            'quantity' => $mode === self::RECEIVE_ALL ? (string) $line->quantity : bcdiv((string) $line->quantity, '2', 3),
        ]);

        if ($mode === self::RECEIVE_PARTIAL) {
            $lines = $lines->take(1);
        }

        $receipts->create($order, [
            'receipt_date' => now()->toDateString(),
            'delivery_note_number' => 'DEMO-DDT-'.$order->number,
            'delivery_note_date' => now()->toDateString(),
        ], $lines->values()->all(), $actor);
    }
}
