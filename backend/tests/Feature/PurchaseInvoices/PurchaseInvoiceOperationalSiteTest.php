<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Address;
use App\Models\OperationalSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** An operational site with the given alias and a primary address without city (label = line1). */
function siteWithAddress(?string $alias, string $line1): OperationalSite
{
    $site = OperationalSite::factory()->create(['alias' => $alias]);
    Address::factory()->primary()->for($site, 'addressable')->create(['line1' => $line1]);

    return $site;
}

it('shows the operational site of a document by alias, falling back to its address', function (?string $alias, string $expected) {
    $site = siteWithAddress($alias, 'Via Roma 1');
    $document = createPurchaseInvoice(['operational_site_id' => $site->id]);

    $this->getJson("/api/purchase-invoices/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.operational_site', ['id' => $site->id, 'name' => $expected]);
})->with([
    'alias' => ['HQ Napoli', 'HQ Napoli'],
    'no alias' => [null, 'Via Roma 1'],
]);

it('the draft from orders labels the operational site by its address when it has no alias', function () {
    $payload = purchaseInvoicePayload();
    $site = siteWithAddress(null, 'Via Milano 2');
    $order = invoiceableOrderOf($payload, PurchaseOrderStatus::Approved, 1);
    $order->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs(purchaseInvoiceUserWith(['create']));

    $this->getJson('/api/purchase-invoices/draft?'.http_build_query(['purchase_order_ids' => [$order->id]]))
        ->assertOk()
        ->assertJsonPath('data.defaults.operational_site', ['id' => $site->id, 'name' => 'Via Milano 2']);
});
