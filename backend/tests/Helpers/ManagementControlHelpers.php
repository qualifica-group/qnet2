<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Enums\InvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QuoteLineType;
use App\Enums\SupplierCommissionDirection;
use App\Models\BusinessFunction;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Shared fixtures of the management control tests (spec 0268). Each test file
// loads this file with require_once: the helpers are not in tests/Pest.php.

if (! function_exists('mcActor')) {
    /** A user holding `management-control.view` and nothing else. */
    function mcActor(bool $withPermission = true): User
    {
        Permission::findOrCreate('management-control.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();

        return $withPermission ? $user->givePermissionTo('management-control.view') : $user;
    }
}

if (! function_exists('mcGet')) {
    /**
     * GET a management control endpoint; the aggregate cache is emptied first so
     * a test that changes data between two calls never reads a stale payload.
     *
     * @param  array<string, mixed>  $params
     */
    function mcGet(string $endpoint, array $params = []): TestResponse
    {
        Cache::flush();

        return test()->getJson("/api/management-control/{$endpoint}".($params === [] ? '' : '?'.http_build_query($params)));
    }
}

if (! function_exists('mcWorkOrder')) {
    /**
     * A commessa of its own offer.
     *
     * @param  array<string, mixed>  $attributes  work order attributes
     * @param  array<string, mixed>  $quote  offer attributes (company_id, commercial_id...)
     */
    function mcWorkOrder(array $attributes = [], array $quote = []): WorkOrder
    {
        $quote = Quote::factory()->create($quote);

        return WorkOrder::factory()->create(['quote_id' => $quote->id, 'start_date' => '2026-03-10', 'type' => 'project', ...$attributes]);
    }
}

if (! function_exists('mcRevenueLine')) {
    /** A REVENUE line of the commessa (or of $quote on a multi-offer one) on a product of a category tied to $function. */
    function mcRevenueLine(WorkOrder $workOrder, string $net, ?BusinessFunction $function = null, ?SupplierCommissionDirection $direction = null, ?Quote $quote = null): QuoteLine
    {
        $category = ProductCategory::factory()->create(['business_function_id' => $function?->id]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $quote?->id ?? $workOrder->quote_id,
            'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            'quantity' => 1,
            'unit_price' => $net,
            'net_amount' => $net,
            'total_amount' => $net,
            'supplier_commission_direction' => $direction,
        ]);
        $workOrder->quoteLines()->attach($line->id);

        return $line;
    }
}

if (! function_exists('mcCostLine')) {
    /** A COST line of the offer, allocated to $revenueLine or generic when null. */
    function mcCostLine(string $net, ?QuoteLine $revenueLine, ?int $quoteId = null): QuoteLine
    {
        return QuoteLine::factory()->create([
            'quote_id' => $revenueLine?->quote_id ?? $quoteId,
            'line_type' => QuoteLineType::Cost,
            'offer_line_id' => $revenueLine?->id,
            'net_amount' => $net,
            'total_amount' => $net,
        ]);
    }
}

if (! function_exists('mcCommission')) {
    function mcCommission(QuoteLine $line, CommissionRecipientRole $role, string $amount): QuoteLineCommission
    {
        return QuoteLineCommission::factory()->create(['quote_line_id' => $line->id, 'recipient_role' => $role, 'calculated_amount' => $amount]);
    }
}

if (! function_exists('mcManualCost')) {
    function mcManualCost(WorkOrder $workOrder, string $net, ?QuoteLine $line = null): WorkOrderCost
    {
        return WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line?->id, 'net_amount' => $net, 'total_amount' => $net]);
    }
}

if (! function_exists('mcInvoice')) {
    /** A receivable document of the commessa with one installment collected for $collected. */
    function mcInvoice(WorkOrder $workOrder, InvoiceType $type, string $net, string $total, ?string $collected = null): Invoice
    {
        $invoice = Invoice::factory()->create(['type' => $type, 'work_order_id' => $workOrder->id, 'net_amount' => $net, 'vat_amount' => '0.00', 'total_amount' => $total]);
        InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => $total, 'collected_amount' => $collected]);

        return $invoice;
    }
}

if (! function_exists('mcOrderLine')) {
    /**
     * An order line of one unit at $amount.
     *
     * @param  array<string, mixed>  $order  purchase order attributes
     */
    function mcOrderLine(?WorkOrder $workOrder, string $amount, PurchaseOrderStatus $status = PurchaseOrderStatus::Approved, array $order = [], array $line = []): PurchaseOrderLine
    {
        return PurchaseOrderLine::factory()->create([
            'purchase_order_id' => PurchaseOrder::factory()->withStatus($status)->create($order)->id,
            'work_order_id' => $workOrder?->id,
            'quantity' => '1.000',
            'unit_price' => $amount,
            'taxable_amount' => $amount,
            ...$line,
        ]);
    }
}

if (! function_exists('mcPayableLine')) {
    /**
     * A payable document line of $net, billing $quantity of $orderLine when given.
     *
     * @param  array<string, mixed>  $document  purchase invoice attributes
     */
    function mcPayableLine(?WorkOrder $workOrder, string $net, ?PurchaseOrderLine $orderLine = null, string $quantity = '1.000', array $document = [], ?PurchaseInvoice $invoice = null, array $line = []): PurchaseInvoiceLine
    {
        return PurchaseInvoiceLine::factory()->create([
            'purchase_invoice_id' => ($invoice ?? PurchaseInvoice::factory()->create($document))->id,
            'purchase_order_line_id' => $orderLine?->id,
            'work_order_id' => $workOrder?->id,
            'quantity' => $quantity,
            'net_amount' => $net,
            ...$line,
        ]);
    }
}
