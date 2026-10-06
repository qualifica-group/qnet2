<?php

declare(strict_types=1);

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('proformaUserWith')) {
    /**
     * A user holding exactly the given proforma-requests abilities, plus the
     * work-orders access (view + viewAll) the work-order-scoped endpoints need.
     *
     * @param  array<int, string>  $abilities
     */
    function proformaUserWith(array $abilities, bool $seesWorkOrders = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("proforma-requests.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("proforma-requests.{$ability}");
        }

        if ($seesWorkOrders) {
            foreach (['viewAny', 'view', 'viewAll'] as $ability) {
                $user->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}"));
            }
        }

        return $user;
    }
}

if (! function_exists('proformaWorkOrder')) {
    /**
     * A work order covering one revenue line per entry. Each entry is the
     * typology code (`consultancy`/`institution`/null for none) and, for an
     * institution, an optional supplier registry.
     *
     * @param  array<int, array{0: string|null, 1?: Registry|null}>  $lines
     */
    function proformaWorkOrder(array $lines, ?PaymentMethod $paymentMethod = null): WorkOrder
    {
        $quote = Quote::factory()->create(['payment_method_id' => $paymentMethod?->id]);
        $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);

        foreach ($lines as $line) {
            $typology = $line[0] === null
                ? ProductTypology::factory()->create()
                : ProductTypology::query()->firstOrCreate(['code' => $line[0]], ['name' => $line[0]]);
            $product = Product::factory()->create([
                'product_typology_id' => $typology->id,
                'supplier_id' => ($line[1] ?? null)?->id,
            ]);
            $quoteLine = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
            $workOrder->quoteLines()->attach($quoteLine->id);
        }

        return $workOrder;
    }
}
