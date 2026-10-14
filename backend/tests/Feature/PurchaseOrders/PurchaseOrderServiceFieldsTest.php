<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Models\Registry;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0259: past approval an order keeps its service fields editable until
// it is cancelled; what the supplier received stays locked.

uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(purchaseOrderUserWith(['view', 'update'])));

it('AC-001: a sent order takes its service fields', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::SentToSupplier);
    $sector = Sector::factory()->create();

    $this->patchJson("/api/purchase-orders/{$order->id}", ['general_notes' => 'Call before delivery', 'sector_ids' => [$sector->id]])
        ->assertOk()
        ->assertJsonPath('data.general_notes', 'Call before delivery')
        ->assertJsonPath('data.sectors.0.id', $sector->id)
        ->assertJsonPath('data.abilities.update', true)
        ->assertJsonPath('data.field_permissions.general_notes.editable', true)
        ->assertJsonPath('data.field_permissions.description.editable', false);
});

it('AC-001: a sent order refuses what the supplier received, lines included', function (array $payload) {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::SentToSupplier);

    $this->patchJson("/api/purchase-orders/{$order->id}", $payload)->assertStatus(409);
})->with([
    'description' => [['description' => 'Changed']],
    'supplier' => [fn () => ['supplier_id' => Registry::factory()->supplier()->create()->id]],
    'lines' => [['lines' => [['description' => 'New', 'cost_type_id' => 1, 'quantity' => 1, 'unit_price' => 1]]]],
]);

it('AC-002: a cancelled order refuses every change and has no editable cell', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Cancelled);

    $this->patchJson("/api/purchase-orders/{$order->id}", ['general_notes' => 'Late'])->assertStatus(409);

    $row = collect($this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10])->json('items'))->firstWhere('id', $order->id);
    expect($row['editable'])->toBeFalse();
});

it('AC-003: an approved row exposes only the service columns and refuses the others', function () {
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);
    $draft = purchaseOrderWithLines();

    $rows = collect($this->postJson('/api/tables/purchase-orders/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$order->id]['editable'])->toBeTrue()
        ->and($rows[$order->id]['editable_columns'])->toEqualCanonicalizing([
            'operational_site', 'payment_method', 'customer', 'work_order', 'quote', 'opportunity', 'sectors', 'is_internal_user',
        ])
        ->and($rows[$draft->id]['editable_columns'])->toBeNull();

    patchPurchaseOrderCell($order, 'is_internal_user', true)->assertOk()->assertJsonPath('data.is_internal_user', true);
    patchPurchaseOrderCell($order, 'description', 'Nope')->assertForbidden();
});
