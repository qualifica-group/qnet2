<?php

declare(strict_types=1);

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Enums\ProductUsage;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\ProductTypology;
use App\Models\PurchaseRequestLine;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0320 D-23 / AC-030: a COST row (offer cost lines, commessa costs) whose
 * supplier carries a Supplier commission on the product costs 0 — enforced on
 * new rows or rows whose product/supplier changed. REVENUE rows, historic rows,
 * purchase requests and orders are untouched.
 */

/**
 * A Cost+Sale product with two suppliers: one paid through a commission, one free.
 *
 * @return array{0: Product, 1: Registry, 2: Registry}
 */
function costZeroProduct(): array
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $paid = Registry::factory()->supplier()->create();
    $free = Registry::factory()->supplier()->create();
    $product = productWithSupplier([
        'category_id' => $category->id,
        'usages' => [ProductUsage::Sale, ProductUsage::Cost],
        'product_typology_id' => ProductTypology::factory()->supplierCommission()->create()->id,
    ], $paid);
    ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $free->id]);
    CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Supplier,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => $product->id,
        'recipient_type' => 'registry',
        'recipient_id' => $paid->id,
    ]);

    return [$product, $paid, $free];
}

function costZeroQuoteActor(): User
{
    foreach (['view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}", 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['quotes.view', 'quotes.create', 'quotes.update', 'quotes.viewAll']);

    return $user;
}

/** @return array<string, mixed> */
function costZeroQuotePayload(Product $product, array $cost, ?int $offerSupplierId = null): array
{
    QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

    return [
        'title' => 'Cost zero',
        'opportunity_id' => Opportunity::factory()->create(['commercial_id' => Referent::factory()->create()->id])->id,
        'offer_lines' => [['product_id' => $product->id, 'supplier_id' => $offerSupplierId, 'quantity' => 1, 'unit_price' => 100]],
        'cost_lines' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 30, ...$cost]],
    ];
}

it('AC-030 offer: a new COST row with a commission supplier is saved at 0, a free supplier keeps its price, REVENUE is untouched', function () {
    [$product, $paid, $free] = costZeroProduct();
    Sanctum::actingAs(costZeroQuoteActor());

    $created = $this->postJson('/api/quotes', costZeroQuotePayload($product, ['supplier_id' => $paid->id], $paid->id))->assertCreated();
    expect($created->json('data.cost_lines.0.unit_price'))->toBe('0.00')
        ->and($created->json('data.cost_lines.0.net_amount'))->toBe('0.00')
        ->and($created->json('data.offer_lines.0.unit_price'))->toBe('100.00');

    $other = $this->postJson('/api/quotes', costZeroQuotePayload($product, ['supplier_id' => $free->id], $free->id))->assertCreated();
    expect($other->json('data.cost_lines.0.unit_price'))->toBe('30.00')
        ->and($other->json('data.cost_lines.0.net_amount'))->toBe('60.00');
});

it('AC-030 offer: switching a COST row to a commission supplier zeroes it, an untouched historic row is not touched', function () {
    [$product, $paid, $free] = costZeroProduct();
    Sanctum::actingAs(costZeroQuoteActor());
    $created = $this->postJson('/api/quotes', costZeroQuotePayload($product, ['supplier_id' => $free->id], $free->id))->assertCreated();
    $quoteId = $created->json('data.id');
    $costId = $created->json('data.cost_lines.0.id');
    $costRow = fn (int $supplierId, int|float $price): array => ['cost_lines' => [['id' => $costId, 'product_id' => $product->id, 'supplier_id' => $supplierId, 'quantity' => 2, 'unit_price' => $price]]];

    // Same pair resubmitted with a new price: free supplier, nothing forced.
    $this->patchJson("/api/quotes/{$quoteId}", $costRow($free->id, 45))->assertOk();
    expect(QuoteLine::findOrFail($costId)->unit_price)->toBe('45.00');

    // Supplier changed to the commission one: forced to 0.
    $this->patchJson("/api/quotes/{$quoteId}", $costRow($paid->id, 45))->assertOk();
    expect(QuoteLine::findOrFail($costId)->unit_price)->toBe('0.00');

    // Historic: the commission supplier already stored, same pair, price edited: not touched.
    $this->patchJson("/api/quotes/{$quoteId}", $costRow($paid->id, 12))->assertOk();
    expect(QuoteLine::findOrFail($costId)->unit_price)->toBe('12.00');
});

it('AC-030 work order costs: a new cost with a commission supplier is saved at 0, a historic one is not touched', function () {
    [$product, $paid, $free] = costZeroProduct();
    $workOrder = WorkOrder::factory()->create();
    Permission::findOrCreate('work-orders.manageCosts', 'web');
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));
    $row = fn (int $supplierId, array $extra = []): array => [
        'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 30, 'vat_rate_id' => null, 'quote_line_id' => null,
        'incurred_on' => '2026-10-01', 'supplier_id' => $supplierId, 'document_reference' => null, 'additional_description' => null, ...$extra,
    ];

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [$row($paid->id), $row($free->id)]])->assertOk();
    $costs = $workOrder->costs()->orderBy('sort_order')->get();
    expect($costs[0]->unit_price)->toBe('0.00')
        ->and($costs[1]->unit_price)->toBe('30.00');

    // The free-supplier row moves to the commission supplier: forced to 0.
    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [$row($paid->id, ['id' => $costs[0]->id, 'unit_price' => 20]), $row($paid->id, ['id' => $costs[1]->id])]])->assertOk();
    expect($costs[1]->fresh()->unit_price)->toBe('0.00')
        ->and($costs[0]->fresh()->unit_price)->toBe('20.00');
});

it('AC-030 purchase requests are not touched: a commission supplier keeps its unit price', function () {
    [$product, $paid] = costZeroProduct();
    Notification::fake();
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));
    $payload = purchaseRequestPayload();
    $payload['lines'][0] = [...$payload['lines'][0], 'product_id' => $product->id, 'supplier_id' => $paid->id, 'unit_price' => '30'];

    $this->postJson('/api/purchase-requests', $payload)->assertCreated();

    expect(PurchaseRequestLine::query()->sole()->unit_price)->toBe('30.00');
});
