<?php

use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\ProformaRequest;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * The typology proforma_kind, not its code, routes lines into proforma
 * requests and invoice drafts (spec 0247, D-6, AC-010).
 */
uses(RefreshDatabase::class);

function attachTypologyLine(WorkOrder $workOrder, ProductTypology $typology, ?Registry $supplier = null): QuoteLine
{
    $product = Product::factory()->create(['product_typology_id' => $typology->id, 'supplier_id' => $supplier?->id]);
    $line = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id, 'product_id' => $product->id]);
    $workOrder->quoteLines()->attach($line->id);

    return $line;
}

it('AC-010: a new typology with proforma_kind=institution raises one institution request per supplier, one without kind is ignored', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $publicBody = ProductTypology::factory()->create(['code' => 'public_body', 'proforma_kind' => 'institution']);
    $untyped = ProductTypology::factory()->create(['code' => 'training', 'proforma_kind' => null]);
    $supplierA = Registry::factory()->create();
    $supplierB = Registry::factory()->create();
    $workOrder = proformaWorkOrder([]);
    attachTypologyLine($workOrder, $publicBody, $supplierA);
    attachTypologyLine($workOrder, $publicBody, $supplierB);
    attachTypologyLine($workOrder, $untyped, $supplierA);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get();
    expect($rows->pluck('kind')->map->value->unique()->values()->all())->toBe(['institution'])
        ->and($rows->pluck('supplier_id')->sort()->values()->all())->toBe(collect([$supplierA->id, $supplierB->id])->sort()->values()->all());
});

it('AC-010: the code no longer decides, a consultancy-coded typology without kind bills nothing', function () {
    Sanctum::actingAs(proformaUserWith(['create']));
    $legacy = ProductTypology::factory()->create(['code' => 'consultancy', 'proforma_kind' => null]);
    $workOrder = proformaWorkOrder([]);
    attachTypologyLine($workOrder, $legacy);

    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])
        ->assertStatus(422)->assertJsonPath('message', 'No billable lines on this work order.');
});

it('AC-010: the invoice draft offers the lines of a typology with the request kind and skips typologies without one', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $publicBody = ProductTypology::factory()->create(['code' => 'public_body', 'proforma_kind' => 'institution']);
    $untyped = ProductTypology::factory()->create(['proforma_kind' => null]);
    $supplier = Registry::factory()->create();
    $workOrder = proformaWorkOrder([]);
    $offered = attachTypologyLine($workOrder, $publicBody, $supplier);
    attachTypologyLine($workOrder, $untyped, $supplier);
    $request = invoiceRequest('institution', $supplier, $workOrder);

    $lines = $this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()->json('data.available_lines');

    expect(array_column($lines, 'quote_line_id'))->toBe([$offered->id]);
});
