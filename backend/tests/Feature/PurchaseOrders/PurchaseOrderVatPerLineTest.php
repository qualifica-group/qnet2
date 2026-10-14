<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Regression (spec 0253, AC-005): purchase requests and orders are not fiscal
 * documents, so their VAT stays per line and never per rate.
 */
it('AC-005: an order keeps the VAT per line on lines that a per-rate split would change', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'create']));
    $payload = purchaseOrderPayload(lineCount: 3);
    $payload['lines'] = array_map(fn (array $line): array => [...$line, 'quantity' => '1', 'unit_price' => '10.01'], $payload['lines']);

    $this->postJson('/api/purchase-orders', $payload)->assertCreated()
        ->assertJsonPath('data.taxable_total', '30.03')
        ->assertJsonPath('data.vat_total', '6.60')
        ->assertJsonPath('data.grand_total', '36.63')
        ->assertJsonPath('data.lines.0.vat_amount', '2.20');
});

it('AC-005: a request keeps the VAT per line', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'create']));
    $payload = purchaseRequestPayload(lineCount: 3);
    $payload['lines'] = array_map(fn (array $line): array => [...$line, 'quantity' => '1', 'unit_price' => '10.01'], $payload['lines']);

    $this->postJson('/api/purchase-requests', $payload)->assertCreated()
        ->assertJsonPath('data.taxable_total', '30.03')
        ->assertJsonPath('data.vat_total', '6.60')
        ->assertJsonPath('data.grand_total', '36.63');
});
