<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\PurchaseOrders\PurchaseOrderService;
use Illuminate\Database\Seeder;

/**
 * Seed fake purchase orders (spec 0210): a draft with two lines, two approved
 * and two already sent to the supplier (the invoiceable ones feed the demo
 * payable documents, one document per order line). Idempotent: each order is keyed by its
 * description. Skipped quietly when the reference data it hangs on (a user, a
 * company site, a supplier registry, the cost types of the migration) is missing.
 */
class DemoPurchaseOrderSeeder extends Seeder
{
    public function run(PurchaseOrderService $orders): void
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

        $this->order($orders, $actor, 'Consulenza e canone sede (demo)', PurchaseOrderStatus::Draft, $header, [
            $line('Consulenza specialistica', '10', '85.00', $costTypes->first()),
            $line('Canone mensile sede', '1', '1200.00', $costTypes->last()),
        ]);
        $this->order($orders, $actor, 'Utenze del trimestre (demo)', PurchaseOrderStatus::Approved, $header, [
            $line('Energia elettrica', '3', '240.00', $costTypes->last()),
        ]);
        $this->order($orders, $actor, 'Collaborazione esterna (demo)', PurchaseOrderStatus::SentToSupplier, $header, [
            $line('Prestazione professionale', '1', '3500.00', $costTypes->first()),
        ]);
        $this->order($orders, $actor, 'Forniture ufficio (demo)', PurchaseOrderStatus::Approved, $header, [
            $line('Cancelleria e consumabili', '5', '60.00', $costTypes->last()),
        ]);
        $this->order($orders, $actor, 'Manutenzione impianti (demo)', PurchaseOrderStatus::SentToSupplier, $header, [
            $line('Intervento di manutenzione', '2', '450.00', $costTypes->first()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function order(PurchaseOrderService $orders, User $actor, string $description, PurchaseOrderStatus $status, array $header, array $lines): void
    {
        if (PurchaseOrder::query()->where('description', $description)->exists()) {
            return;
        }

        $order = $orders->createOne($header + ['description' => $description], $lines, null, $actor);
        $order->forceFill(['status' => $status])->save();
    }
}
