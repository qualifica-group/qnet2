<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus as LineStatus;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A user who can create orders and holds the given purchase-requests abilities.
 *
 * @param  array<int, string>  $requestAbilities
 */
function generatorActor(array $requestAbilities = ['fulfill', 'view', 'viewAll']): User
{
    $user = purchaseRequestUserWith($requestAbilities);
    purchaseOrderUserWith([]);
    $user->givePermissionTo('purchase-orders.create');

    return $user;
}

/**
 * Approved request lines (one request) with the given supplier each.
 *
 * @param  array<int, Registry|null>  $suppliers
 * @param  array<string, mixed>  $attributes  request attributes
 * @return array<int, PurchaseRequestLine>
 */
function approvedLines(array $suppliers, array $attributes = []): array
{
    $request = purchaseRequestWithLines(array_fill(0, count($suppliers), LineStatus::Approved), $attributes);

    foreach ($request->lines as $index => $line) {
        $line->update(['supplier_id' => $suppliers[$index]?->id, 'quantity' => '2.000', 'unit_price' => '10.00']);
    }

    return $request->fresh('lines')->lines->all();
}

/**
 * @param  array<int, int>  $lineIds
 * @param  array<string, mixed>  $extra
 */
function generateOrders(object $test, array $lineIds, array $extra = []): TestResponse
{
    return $test->postJson('/api/purchase-request-lines/purchase-orders', ['line_ids' => $lineIds, 'cost_type_id' => CostType::factory()->create()->id] + $extra);
}

it('AC-007: groups approved lines by supplier into draft orders and orders the request lines', function () {
    Sanctum::actingAs($actor = generatorActor());
    $rossi = supplierRegistry();
    $bianchi = supplierRegistry();
    $lines = approvedLines([$rossi, $bianchi, $rossi]);
    $costType = CostType::factory()->create();
    $payment = PaymentMethod::factory()->create();
    $ids = array_map(fn (PurchaseRequestLine $line): int => $line->id, $lines);

    $this->postJson('/api/purchase-request-lines/purchase-orders', [
        'line_ids' => $ids, 'cost_type_id' => $costType->id, 'payment_method_id' => $payment->id, 'order_date' => '2026-10-12',
    ])->assertCreated()
        ->assertJsonCount(2, 'data.purchase_orders')
        ->assertJsonPath('data.purchase_orders.0.supplier.id', $rossi->id)
        ->assertJsonPath('data.purchase_orders.0.lines_count', 2)
        ->assertJsonPath('data.purchase_orders.1.lines_count', 1)
        ->assertJsonPath('data.closed_purchase_request_ids', []);

    $orders = PurchaseOrder::query()->with('lines')->orderBy('id')->get();
    $request = $lines[0]->purchaseRequest;

    expect($orders)->toHaveCount(2)
        ->and($orders[0]->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($orders[0]->company_id)->toBe($request->company_id)
        ->and($orders[0]->company_site_id)->toBe($request->company_site_id)
        ->and($orders[0]->operational_site_id)->toBe($request->operational_site_id)
        ->and($orders[0]->payment_method_id)->toBe($payment->id)
        ->and($orders[0]->order_date->toDateString())->toBe('2026-10-12')
        ->and($orders[0]->description)->toBe("Da RDA #{$request->id}")
        ->and($orders[0]->created_by)->toBe($actor->id)
        ->and($orders[0]->lines->pluck('purchase_request_line_id')->all())->toBe([$lines[0]->id, $lines[2]->id])
        ->and($orders[0]->lines->pluck('cost_type_id')->unique()->all())->toBe([$costType->id])
        ->and($orders[0]->lines[0]->description)->toBe($lines[0]->description)
        ->and($orders[0]->grand_total)->toBe('40.00');

    $logs = PurchaseRequestLineStatusLog::query()->whereIn('purchase_request_line_id', $ids)->get();

    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('bulk_group_id')->unique())->toHaveCount(1)
        ->and($logs->pluck('bulk_group_id')->first())->not->toBeNull()
        ->and($logs->pluck('to_status')->unique()->all())->toBe([LineStatus::Ordered]);

    foreach ($lines as $line) {
        $order = $orders->first(fn (PurchaseOrder $candidate): bool => $candidate->lines->contains('purchase_request_line_id', $line->id));

        expect($line->fresh()->status)->toBe(LineStatus::Ordered)
            ->and($line->fresh()->oda_reference)->toBe($order->code());
    }
});

it('AC-007: requests with the same company and sites share one order, other sites split it', function () {
    Sanctum::actingAs(generatorActor());
    $supplier = supplierRegistry();
    $first = approvedLines([$supplier]);
    $same = approvedLines([$supplier]);
    $other = approvedLines([$supplier]);
    $same[0]->purchaseRequest->forceFill([
        'company_id' => $first[0]->purchaseRequest->company_id,
        'company_site_id' => $first[0]->purchaseRequest->company_site_id,
        'operational_site_id' => $first[0]->purchaseRequest->operational_site_id,
    ])->save();

    generateOrders($this, [$first[0]->id, $same[0]->id, $other[0]->id])->assertCreated()->assertJsonCount(2, 'data.purchase_orders');

    $order = PurchaseOrder::query()->orderBy('id')->first();

    expect($order->description)->toBe("Da RDA #{$first[0]->purchase_request_id}, #{$same[0]->purchase_request_id}")
        ->and($order->lines)->toHaveCount(2);
});

it('AC-008: a line not approved, without supplier or already linked refuses the whole batch with 422', function (string $case) {
    Sanctum::actingAs(generatorActor());
    $supplier = supplierRegistry();
    [$good, $bad] = approvedLines([$supplier, $supplier]);

    if ($case === 'linked') {
        generateOrders($this, [$bad->id])->assertCreated();
        $bad->forceFill(['status' => LineStatus::Approved])->save();
    } else {
        $bad->forceFill($case === 'pending' ? ['status' => LineStatus::PendingApproval] : ['supplier_id' => null])->save();
    }

    $before = PurchaseOrder::query()->count();

    generateOrders($this, [$good->id, $bad->id])->assertUnprocessable()->assertJsonValidationErrors(['line_ids.1']);

    expect(PurchaseOrder::query()->count())->toBe($before)
        ->and($good->fresh()->status)->toBe(LineStatus::Approved)
        ->and($good->fresh()->oda_reference)->toBeNull();
})->with(['pending', 'no_supplier', 'linked']);

it('AC-008: an actor without the fulfill or manage capability is refused with 403 and nothing changes', function (array $abilities) {
    Sanctum::actingAs(generatorActor($abilities));
    [$line] = approvedLines([supplierRegistry()]);

    generateOrders($this, [$line->id])->assertForbidden();

    expect(PurchaseOrder::query()->count())->toBe(0)
        ->and($line->fresh()->status)->toBe(LineStatus::Approved);
})->with([[['view', 'viewAll']], [['view', 'viewAll', 'close']]]);

it('AC-008: manageStatuses is enough to order, and creating orders is still required', function () {
    [$line] = approvedLines([supplierRegistry()]);

    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'manageStatuses']));
    generateOrders($this, [$line->id])->assertForbidden();
    expect(PurchaseOrder::query()->count())->toBe(0);

    Sanctum::actingAs(generatorActor(['view', 'viewAll', 'manageStatuses']));
    generateOrders($this, [$line->id])->assertCreated();
});

it('AC-008: a line of a closed request is refused with 409', function () {
    Sanctum::actingAs(generatorActor());
    [$line] = approvedLines([supplierRegistry()], ['status' => 'closed']);

    generateOrders($this, [$line->id])->assertStatus(409);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('rejects an invalid payload', function (array $payload, string $field) {
    Sanctum::actingAs(generatorActor());
    [$line] = approvedLines([supplierRegistry()]);
    $valid = ['line_ids' => [$line->id], 'cost_type_id' => CostType::factory()->create()->id];

    $this->postJson('/api/purchase-request-lines/purchase-orders', $payload + $valid)->assertUnprocessable()->assertJsonValidationErrors([$field]);
})->with([
    'no lines' => [['line_ids' => []], 'line_ids'],
    'duplicate lines' => [['line_ids' => [1, 1]], 'line_ids.0'],
    'unknown line' => [['line_ids' => [999999]], 'line_ids.0'],
    'no cost type' => [['cost_type_id' => null], 'cost_type_id'],
    'bad date' => [['order_date' => '12/10/2026'], 'order_date'],
]);

it('rejects an inactive cost type', function () {
    Sanctum::actingAs(generatorActor());
    [$line] = approvedLines([supplierRegistry()]);

    $this->postJson('/api/purchase-request-lines/purchase-orders', ['line_ids' => [$line->id], 'cost_type_id' => CostType::factory()->create(['is_active' => false])->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['cost_type_id']);
});

it('AC-009: generating and then deleting the order releases the request lines (complete link)', function () {
    $actor = generatorActor();
    $actor->givePermissionTo('purchase-orders.delete');
    Sanctum::actingAs($actor);
    [$line] = approvedLines([supplierRegistry()]);

    generateOrders($this, [$line->id])->assertCreated();
    $order = PurchaseOrder::query()->sole();
    expect($line->fresh()->status)->toBe(LineStatus::Ordered)->and($line->fresh()->oda_reference)->toBe($order->code());

    $this->deleteJson("/api/purchase-orders/{$order->id}")->assertOk();

    expect($line->fresh()->status)->toBe(LineStatus::Approved)
        ->and($line->fresh()->oda_reference)->toBeNull();
});

it('exposes the purchase order of a request line in the RDA detail and in the lines grid, without N+1', function () {
    Sanctum::actingAs(generatorActor());
    $supplier = supplierRegistry();
    $lines = approvedLines([$supplier, $supplier, $supplier]);
    $request = $lines[0]->purchaseRequest;

    generateOrders($this, [$lines[0]->id, $lines[1]->id])->assertCreated();
    $order = PurchaseOrder::query()->sole();
    $reference = ['id' => $order->id, 'code' => $order->code()];

    $detail = $this->getJson("/api/purchase-requests/{$request->id}")->assertOk()->json('data.lines');
    expect(collect($detail)->pluck('purchase_order')->all())->toBe([$reference, $reference, null]);

    $grid = fn () => $this->postJson('/api/tables/purchase-request-lines/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items');

    DB::enableQueryLog();
    $byId = collect($grid())->keyBy('id');
    $queries = count(DB::getQueryLog());
    DB::flushQueryLog();

    expect($byId[$lines[0]->id]['purchase_order'])->toBe($reference)
        ->and($byId[$lines[2]->id]['purchase_order'])->toBeNull()
        ->and($byId[$lines[0]->id]['oda_reference'])->toBe($order->code());

    approvedLines([supplierRegistry(), supplierRegistry()]);
    DB::flushQueryLog();
    $grid();

    expect(count(DB::getQueryLog()))->toBe($queries);
});
