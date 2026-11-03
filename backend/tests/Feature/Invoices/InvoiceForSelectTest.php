<?php

use App\Models\Invoice;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/invoices/for-select (ADR 0011, spec 0347 AC-018): same rows as the
 * `invoices` index (no row scope), gated by auth:sanctum alone.
 */
uses(RefreshDatabase::class);

it('returns {id, label, subtitle} with number/year and customer plus date, newest first', function () {
    $customer = Registry::factory()->create(['name' => 'Acme Srl']);
    Invoice::factory()->create(['number' => 3, 'year' => 2026, 'document_date' => '2026-01-10']);
    $newest = Invoice::factory()->create(['number' => 7, 'year' => 2026, 'document_date' => '2026-03-05', 'customer_registry_id' => $customer->id]);
    Sanctum::actingAs(User::factory()->create());

    $items = $this->getJson('/api/invoices/for-select')->assertOk()->json('items');

    expect($items[0])->toMatchArray(['id' => $newest->id, 'label' => '7/2026', 'subtitle' => 'Acme Srl · 2026-03-05'])
        ->and($items)->toHaveCount(2);
});

it('searches by number, number/year, external number and customer name', function () {
    $byNumber = Invoice::factory()->create(['number' => 42, 'year' => 2026, 'external_number' => null]);
    Invoice::factory()->create(['number' => 42, 'year' => 2025, 'external_number' => null]);
    $byExternal = Invoice::factory()->create(['number' => 900, 'year' => 2026, 'external_number' => 'FT-XYZ']);
    $byCustomer = Invoice::factory()->create(['number' => 901, 'year' => 2026, 'customer_registry_id' => Registry::factory()->create(['name' => 'Zeta Holding'])->id]);
    Sanctum::actingAs(User::factory()->create());

    expect(array_column($this->getJson('/api/invoices/for-select?search=42/2026')->assertOk()->json('items'), 'id'))->toBe([$byNumber->id]);
    expect($this->getJson('/api/invoices/for-select?search=FT-XYZ')->json('items.0.id'))->toBe($byExternal->id);
    expect($this->getJson('/api/invoices/for-select?search=Zeta')->json('items.0.id'))->toBe($byCustomer->id);
});

it('paginates, skips the count with include_total=0 and hydrates ids[] without inflating the total', function () {
    $invoices = Invoice::factory()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $page = $this->getJson('/api/invoices/for-select?limit=2')->assertOk();
    expect($page->json('items'))->toHaveCount(2)->and($page->json('pagination.total'))->toBe(3);

    $noTotal = $this->getJson('/api/invoices/for-select?limit=2&include_total=0')->assertOk();
    expect($noTotal->json('pagination.total'))->toBeNull()->and($noTotal->json('pagination.has_more'))->toBeTrue();

    $held = $invoices->sortBy('document_date')->first();
    $hydrated = $this->getJson("/api/invoices/for-select?search=zzz-no-match&ids[]={$held->id}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->toBe([$held->id])->and($hydrated->json('pagination.total'))->toBe(0);
});

it('rejects an unauthenticated caller and an invalid limit', function () {
    $this->getJson('/api/invoices/for-select')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/invoices/for-select?limit=500')->assertUnprocessable();
});
