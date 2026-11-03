<?php

use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/purchase-invoices/for-select (ADR 0011, spec 0347 AC-018): same rows
 * as the `purchase-invoices` index (no row scope), gated by auth:sanctum alone.
 */
uses(RefreshDatabase::class);

it('returns {id, label, subtitle} with the document number and supplier plus date', function () {
    $supplier = Registry::factory()->create(['name' => 'Fornitore Uno']);
    $document = PurchaseInvoice::factory()->create(['document_number' => 'FA-77', 'document_date' => '2026-02-01', 'supplier_id' => $supplier->id]);
    Sanctum::actingAs(User::factory()->create());

    $items = $this->getJson('/api/purchase-invoices/for-select')->assertOk()->json('items');

    expect($items[0])->toMatchArray(['id' => $document->id, 'label' => 'FA-77', 'subtitle' => 'Fornitore Uno · 2026-02-01']);
});

it('labels a document without a number as #id', function () {
    $draft = PurchaseInvoice::factory()->create(['document_number' => null]);
    Sanctum::actingAs(User::factory()->create());

    expect($this->getJson('/api/purchase-invoices/for-select')->assertOk()->json('items.0.label'))->toBe('#'.$draft->id);
});

it('searches by document number and supplier name', function () {
    $byNumber = PurchaseInvoice::factory()->create(['document_number' => 'ZZ-100']);
    $bySupplier = PurchaseInvoice::factory()->create(['document_number' => 'AA-1', 'supplier_id' => Registry::factory()->create(['name' => 'Omega Spa'])->id]);
    PurchaseInvoice::factory()->create(['document_number' => 'AA-2']);
    Sanctum::actingAs(User::factory()->create());

    expect(array_column($this->getJson('/api/purchase-invoices/for-select?search=ZZ-100')->json('items'), 'id'))->toBe([$byNumber->id]);
    expect(array_column($this->getJson('/api/purchase-invoices/for-select?search=Omega')->json('items'), 'id'))->toBe([$bySupplier->id]);
});

it('paginates, skips the count with include_total=0 and hydrates ids[]', function () {
    $documents = PurchaseInvoice::factory()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $page = $this->getJson('/api/purchase-invoices/for-select?limit=2')->assertOk();
    expect($page->json('items'))->toHaveCount(2)->and($page->json('pagination.total'))->toBe(3);

    $noTotal = $this->getJson('/api/purchase-invoices/for-select?limit=2&include_total=0')->assertOk();
    expect($noTotal->json('pagination.total'))->toBeNull()->and($noTotal->json('pagination.has_more'))->toBeTrue();

    $held = $documents->first()->id;
    $hydrated = $this->getJson("/api/purchase-invoices/for-select?search=zzz-no-match&ids[]={$held}")->assertOk();
    expect(array_column($hydrated->json('items'), 'id'))->toBe([$held])->and($hydrated->json('pagination.total'))->toBe(0);
});

it('rejects an unauthenticated caller', function () {
    $this->getJson('/api/purchase-invoices/for-select')->assertUnauthorized();
});
