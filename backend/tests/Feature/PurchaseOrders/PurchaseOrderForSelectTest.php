<?php

use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/purchase-orders/for-select (ADR 0011, spec 0347 AC-018): same rows as
 * the `purchase-orders` index (no row scope, D-20), gated by auth:sanctum alone.
 */
uses(RefreshDatabase::class);

it('returns {id, label, subtitle} with number/year and supplier plus order date', function () {
    $supplier = Registry::factory()->create(['name' => 'Fornitore Due']);
    $order = PurchaseOrder::factory()->create(['number' => 12, 'year' => 2026, 'order_date' => '2026-04-02', 'supplier_id' => $supplier->id]);
    Sanctum::actingAs(User::factory()->create());

    $items = $this->getJson('/api/purchase-orders/for-select')->assertOk()->json('items');

    expect($items[0])->toMatchArray(['id' => $order->id, 'label' => '12/2026', 'subtitle' => 'Fornitore Due · 2026-04-02']);
});

it('searches by number, number/year, description and supplier name', function () {
    $byNumber = PurchaseOrder::factory()->create(['number' => 55, 'year' => 2026, 'description' => 'alfa']);
    PurchaseOrder::factory()->create(['number' => 55, 'year' => 2025, 'description' => 'beta']);
    $byDescription = PurchaseOrder::factory()->create(['number' => 800, 'year' => 2026, 'description' => 'Toner speciale']);
    $bySupplier = PurchaseOrder::factory()->create(['number' => 801, 'year' => 2026, 'supplier_id' => Registry::factory()->create(['name' => 'Sigma Srl'])->id]);
    Sanctum::actingAs(User::factory()->create());

    expect(array_column($this->getJson('/api/purchase-orders/for-select?search=55/2026')->json('items'), 'id'))->toBe([$byNumber->id]);
    expect($this->getJson('/api/purchase-orders/for-select?search=Toner')->json('items.0.id'))->toBe($byDescription->id);
    expect($this->getJson('/api/purchase-orders/for-select?search=Sigma')->json('items.0.id'))->toBe($bySupplier->id);
});

it('paginates, skips the count with include_total=0 and hydrates ids[]', function () {
    $orders = PurchaseOrder::factory()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $page = $this->getJson('/api/purchase-orders/for-select?limit=2')->assertOk();
    expect($page->json('items'))->toHaveCount(2)->and($page->json('pagination.total'))->toBe(3);

    $noTotal = $this->getJson('/api/purchase-orders/for-select?limit=2&include_total=0')->assertOk();
    expect($noTotal->json('pagination.total'))->toBeNull()->and($noTotal->json('pagination.has_more'))->toBeTrue();

    $held = $orders->first()->id;
    $hydrated = $this->getJson("/api/purchase-orders/for-select?search=zzz-no-match&ids[]={$held}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->toBe([$held])->and($hydrated->json('pagination.total'))->toBe(0);
});

it('rejects an unauthenticated caller', function () {
    $this->getJson('/api/purchase-orders/for-select')->assertUnauthorized();
});
