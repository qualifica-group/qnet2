<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0257: the ODA saves one field (or one line) at a time through a
// partial PATCH, and the grid edits its header cells through the form's own
// PurchaseOrderWriteRequest + PurchaseOrderService (PurchaseOrderCellWriter).

uses(RefreshDatabase::class);

function patchPurchaseOrderCell(PurchaseOrder $order, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/purchase-orders/rows/{$order->id}", ['column' => $column, 'value' => $value]);
}

beforeEach(fn () => Sanctum::actingAs(purchaseOrderUserWith(['view', 'update'])));

it('AC-001: updates the description alone and leaves lines and sectors untouched', function () {
    $order = purchaseOrderWithLines(lineCount: 2);
    $sector = Sector::factory()->create();
    $order->sectors()->sync([$sector->id]);
    $lineIds = $order->lines->pluck('id')->all();

    $this->patchJson("/api/purchase-orders/{$order->id}", ['description' => 'Monthly toner'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Monthly toner')
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.sectors.0.id', $sector->id);

    expect($order->fresh('lines')->lines->pluck('id')->all())->toBe($lineIds);
});

it('AC-001: a lines-only patch edits the line and keeps the header', function () {
    $order = purchaseOrderWithLines();
    $line = $order->lines->first();

    $this->patchJson("/api/purchase-orders/{$order->id}", ['lines' => [[
        'id' => $line->id, 'description' => 'Edited', 'cost_type_id' => $line->cost_type_id, 'quantity' => '3', 'unit_price' => '5',
    ]]])
        ->assertOk()
        ->assertJsonPath('data.description', $order->description)
        ->assertJsonPath('data.lines.0.description', 'Edited')
        ->assertJsonPath('data.lines.0.taxable_amount', '15.00');
});

it('AC-002: a site of another company and a new company are refused', function () {
    $order = purchaseOrderWithLines();

    $this->patchJson("/api/purchase-orders/{$order->id}", ['company_site_id' => CompanySite::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('company_site_id');

    $company = Company::factory()->create();
    $this->patchJson("/api/purchase-orders/{$order->id}", [
        'company_id' => $company->id,
        'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id])->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('company_id');
});

it('AC-002: a partial patch is refused once the order left the editable statuses', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    $this->patchJson("/api/purchase-orders/{$order->id}", ['description' => 'Late'])->assertStatus(409);
});

it('AC-003: declares the editable header columns and no edit row action', function () {
    purchaseOrderWithLines();
    $columns = collect($this->getJson('/api/tables/purchase-orders/columns')->assertOk()->json('data.columns'));

    expect($columns->where('editable', true)->pluck('id')->sort()->values()->all())->toBe([
        'company_site', 'customer', 'description', 'is_internal_user', 'operational_site', 'opportunity', 'order_date',
        'payment_method', 'quote', 'sectors', 'supplier', 'work_order',
    ])->and($columns->firstWhere('id', 'supplier')['relation'])->toBe(['resource' => 'registries', 'params' => ['is_supplier' => 1]]);

    $row = $this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');
    expect($row['actions'])->not->toContain('update');
});

it('AC-003: saves header cells through the form path', function () {
    $order = purchaseOrderWithLines();
    $supplier = Registry::factory()->supplier()->create();
    $site = CompanySite::factory()->create(['company_id' => $order->company_id]);
    $operationalSite = OperationalSite::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $customer = Registry::factory()->create();
    $sectors = Sector::factory()->count(2)->create();

    patchPurchaseOrderCell($order, 'description', 'Paper')->assertOk()->assertJsonPath('data.description', 'Paper');
    patchPurchaseOrderCell($order, 'order_date', '2026-11-03')->assertOk()->assertJsonPath('data.order_date', '2026-11-03');
    patchPurchaseOrderCell($order, 'supplier', $supplier->id)->assertOk()->assertJsonPath('data.supplier.id', $supplier->id);
    patchPurchaseOrderCell($order, 'company_site', $site->id)->assertOk()->assertJsonPath('data.company_site.id', $site->id);
    patchPurchaseOrderCell($order, 'operational_site', $operationalSite->id)->assertOk()->assertJsonPath('data.operational_site.id', $operationalSite->id);
    patchPurchaseOrderCell($order, 'payment_method', $paymentMethod->id)->assertOk()->assertJsonPath('data.payment_method.id', $paymentMethod->id);
    patchPurchaseOrderCell($order, 'customer', $customer->id)->assertOk()->assertJsonPath('data.customer.id', $customer->id);
    patchPurchaseOrderCell($order, 'sectors', $sectors->pluck('id')->all())->assertOk()->assertJsonCount(2, 'data.sectors');
    patchPurchaseOrderCell($order, 'is_internal_user', true)->assertOk()->assertJsonPath('data.is_internal_user', true);

    expect($order->fresh('lines')->lines)->toHaveCount(1);
});

it('AC-003: the form rules hold in the cell', function () {
    $order = purchaseOrderWithLines();

    patchPurchaseOrderCell($order, 'supplier', Registry::factory()->create(['is_supplier' => false])->id)->assertUnprocessable();
    patchPurchaseOrderCell($order, 'company_site', CompanySite::factory()->create()->id)->assertUnprocessable();
    patchPurchaseOrderCell($order, 'description', '')->assertUnprocessable();
    patchPurchaseOrderCell($order, 'company', $order->company_id)->assertUnprocessable()->assertJsonValidationErrors('column');
});

it('AC-003: an order outside the editable statuses is a read-only row', function () {
    $locked = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    $draft = purchaseOrderWithLines();

    $rows = collect($this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$locked->id]['editable'])->toBeFalse()
        ->and($rows[$draft->id]['editable'])->toBeTrue();

    patchPurchaseOrderCell($locked, 'description', 'Late')->assertForbidden();
});

it('AC-003: forbids the cell edit without update', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $order = purchaseOrderWithLines();

    patchPurchaseOrderCell($order, 'description', 'Nope')->assertForbidden();
});
