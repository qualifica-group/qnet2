<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('purchaseOrderUserWith')) {
    /**
     * A user holding exactly the given purchase-orders abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function purchaseOrderUserWith(array $abilities): User
    {
        foreach (['view', 'create', 'update', 'delete', 'changeStatus', 'approve', 'merge', 'send', 'receive', 'export', 'viewActivity'] as $ability) {
            // Explicit guard: Sanctum::actingAs() switches the default one to `sanctum`.
            Permission::findOrCreate("purchase-orders.{$ability}", 'web');
        }

        // A test may have checked a permission before this call: drop the stale registry.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("purchase-orders.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('purchaseOrderPayload')) {
    /**
     * A valid write payload with `$lineCount` lines of 2 x 10.005 (22% VAT).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseOrderPayload(array $overrides = [], int $lineCount = 1): array
    {
        $company = Company::factory()->create();
        $vat = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
        $costType = CostType::factory()->create();

        $lines = [];

        for ($i = 1; $i <= $lineCount; $i++) {
            $lines[] = [
                'description' => "Line {$i}",
                'cost_type_id' => $costType->id,
                'quantity' => '2',
                'unit_price' => '10.005',
                'vat_rate_id' => $vat->id,
            ];
        }

        return array_merge([
            'supplier_id' => Registry::factory()->supplier()->create()->id,
            'company_id' => $company->id,
            'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
            'order_date' => '2026-10-09',
            'description' => 'Office rent',
            'lines' => $lines,
        ], $overrides);
    }
}

if (! function_exists('purchaseOrderWithLines')) {
    /**
     * A persisted order with `$lineCount` lines (100.00 each, no VAT) and the matching totals.
     *
     * @param  array<string, mixed>  $attributes  order attributes
     */
    function purchaseOrderWithLines(PurchaseOrderStatus $status = PurchaseOrderStatus::Draft, int $lineCount = 1, array $attributes = []): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->withStatus($status)->create($attributes);

        for ($i = 1; $i <= $lineCount; $i++) {
            PurchaseOrderLine::factory()->create(['purchase_order_id' => $order->id, 'position' => $i]);
        }

        $total = number_format($lineCount * 100, 2, '.', '');
        $order->forceFill(['taxable_total' => $total, 'vat_total' => '0.00', 'grand_total' => $total])->save();

        return $order->fresh('lines');
    }
}

if (! function_exists('purchaseOrderUpdatePayload')) {
    /**
     * A PUT payload that keeps the stored order unchanged (same header, same
     * lines by id): tests then override only what they exercise.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseOrderUpdatePayload(PurchaseOrder $order, array $overrides = []): array
    {
        $payload = [
            'supplier_id' => $order->supplier_id,
            'company_id' => $order->company_id,
            'company_site_id' => $order->company_site_id,
            'order_date' => $order->order_date->toDateString(),
            'description' => $order->description,
            'payment_method_id' => $order->payment_method_id,
            'lines' => $order->lines->map(fn (PurchaseOrderLine $line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'cost_type_id' => $line->cost_type_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ])->all(),
        ];

        return array_merge($payload, $overrides);
    }
}

if (! function_exists('purchaseOrderReceive')) {
    /**
     * Records a receipt and keeps received_quantity in sync, as the receipt service does.
     *
     * @param  array<int, string|int|float>  $quantities  order line id => received quantity
     * @param  array<string, mixed>  $attributes  receipt attributes
     */
    function purchaseOrderReceive(PurchaseOrder $order, array $quantities, array $attributes = []): PurchaseOrderReceipt
    {
        $receipt = PurchaseOrderReceipt::factory()->create(['purchase_order_id' => $order->id] + $attributes);

        foreach ($quantities as $lineId => $quantity) {
            $receipt->lines()->create(['purchase_order_line_id' => $lineId, 'quantity' => $quantity]);
            PurchaseOrderLine::query()->whereKey($lineId)->increment('received_quantity', (float) $quantity);
        }

        return $receipt;
    }
}

if (! function_exists('purchaseOrderReceiptPayload')) {
    /**
     * A POST receipts payload; by default every line of the order in full.
     *
     * @param  array<int, string|int|float>|null  $quantities  order line id => quantity
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function purchaseOrderReceiptPayload(PurchaseOrder $order, ?array $quantities = null, array $overrides = []): array
    {
        $quantities ??= $order->lines->mapWithKeys(fn (PurchaseOrderLine $line): array => [$line->id => $line->quantity])->all();

        $lines = [];

        foreach ($quantities as $lineId => $quantity) {
            $lines[] = ['purchase_order_line_id' => $lineId, 'quantity' => $quantity];
        }

        return array_merge(['receipt_date' => now()->toDateString(), 'lines' => $lines], $overrides);
    }
}

if (! function_exists('purchaseOrderReceivable')) {
    /**
     * A persisted order ready to receive: every line orders `$quantity`.
     */
    function purchaseOrderReceivable(PurchaseOrderStatus $status = PurchaseOrderStatus::SentToSupplier, int $lineCount = 2, string $quantity = '10.000'): PurchaseOrder
    {
        $order = purchaseOrderWithLines($status, $lineCount);
        PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->update(['quantity' => $quantity]);

        return $order->fresh('lines');
    }
}

if (! function_exists('patchPurchaseOrderCell')) {
    /** PATCH one cell of the `purchase-orders` grid (specs 0257, 0259). */
    function patchPurchaseOrderCell(PurchaseOrder $order, string $column, mixed $value): TestResponse
    {
        return test()->patchJson("/api/tables/purchase-orders/rows/{$order->id}", ['column' => $column, 'value' => $value]);
    }
}
