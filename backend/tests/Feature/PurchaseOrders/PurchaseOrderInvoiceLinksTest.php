<?php

use App\Enums\PurchaseInvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: the order lists its linked documents in the minimal shape, ordered by date then id', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $order = purchaseOrderWithLines(PurchaseOrderStatus::Approved);

    $sameDay = PurchaseInvoice::factory()->create(['document_type' => PurchaseInvoiceType::Proforma, 'document_number' => null, 'document_date' => '2026-10-01']);
    $later = PurchaseInvoice::factory()->create(['document_type' => PurchaseInvoiceType::Invoice, 'document_number' => 'FT-45', 'document_date' => '2026-10-01']);
    $earlier = PurchaseInvoice::factory()->create(['document_type' => PurchaseInvoiceType::Proforma, 'document_number' => null, 'document_date' => '2026-09-01']);
    $order->purchaseInvoices()->attach([$sameDay->id, $later->id, $earlier->id]);

    $response = $this->getJson("/api/purchase-orders/{$order->id}")->assertOk();

    expect($response->json('data.purchase_invoices'))->toBe([
        ['id' => $earlier->id, 'document_type' => 'proforma', 'document_number' => null, 'document_date' => '2026-09-01'],
        ['id' => $sameDay->id, 'document_type' => 'proforma', 'document_number' => null, 'document_date' => '2026-10-01'],
        ['id' => $later->id, 'document_type' => 'invoice', 'document_number' => 'FT-45', 'document_date' => '2026-10-01'],
    ]);
});

it('AC-002: an order without documents returns an empty list', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $order = purchaseOrderWithLines();

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.purchase_invoices', []);
});

it('AC-003: a user without purchase-invoices.view still receives the linked documents', function () {
    $user = purchaseOrderUserWith(['view']);
    expect($user->can('purchase-invoices.view'))->toBeFalse();
    Sanctum::actingAs($user);
    $order = purchaseOrderWithLines();
    $order->purchaseInvoices()->attach(PurchaseInvoice::factory()->create()->id);

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.purchase_invoices');
});
