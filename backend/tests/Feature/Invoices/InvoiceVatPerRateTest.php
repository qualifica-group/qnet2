<?php

use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Three lines of 10.01 at 22% plus one exempt line (spec 0253, D-1).
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function threeLinesPayload(array $payload): array
{
    $vat = $payload['lines'][0]['vat_rate_id'];
    $line = ['description' => 'Row', 'quantity' => '1', 'unit_price' => '10.01', 'vat_rate_id' => $vat];
    $exempt = [...$line, 'unit_price' => '5.00', 'vat_rate_id' => VatRate::factory()->create(['rate' => '0.00'])->id];
    $payload['lines'] = [$line, $line, $line, $exempt];

    return $payload;
}

it('AC-003: the header VAT is the VAT per rate and equals the sum of the line VATs', function () {
    Sanctum::actingAs(invoiceUserWith(['view', 'create']));
    $request = invoiceRequest();

    $data = $this->postJson("/api/proforma-requests/{$request->id}/invoice", threeLinesPayload(invoicePayload($request)))
        ->assertCreated()
        ->assertJsonPath('data.net_amount', '35.03')
        ->assertJsonPath('data.vat_amount', '6.61')
        ->assertJsonPath('data.total_amount', '41.64')
        ->json('data');

    expect(array_column($data['lines'], 'vat_amount'))->toBe(['2.21', '2.20', '2.20', '0.00'])
        ->and(array_sum(array_map('floatval', array_column($data['lines'], 'vat_amount'))))->toEqualWithDelta(6.61, 0.0001)
        ->and(array_sum(array_map('floatval', array_column($data['installments'], 'amount'))))->toEqualWithDelta(41.64, 0.0001);
});

it('AC-003: an edit recomputes with the new rule', function () {
    Sanctum::actingAs(invoiceUserWith(['view', 'create', 'update']));
    $request = invoiceRequest();
    $payload = threeLinesPayload(invoicePayload($request));
    $data = $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertCreated()->json('data');

    $this->putJson("/api/invoices/{$data['id']}", $payload)->assertOk()
        ->assertJsonPath('data.total_amount', '41.64');
});

it('AC-007: a unit price with more than 2 decimals is a 422 on the line field, 2 decimals pass', function () {
    Sanctum::actingAs(invoiceUserWith(['view', 'create']));
    $request = invoiceRequest();
    $payload = invoicePayload($request);

    $this->postJson("/api/proforma-requests/{$request->id}/invoice", invoiceWithUnitPrice($payload, '0.333'))
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.unit_price' => [__('invoices.unit_price_decimals')]]);

    $this->postJson("/api/proforma-requests/{$request->id}/invoice", invoiceWithUnitPrice($payload, '0.33'))
        ->assertCreated();
});
