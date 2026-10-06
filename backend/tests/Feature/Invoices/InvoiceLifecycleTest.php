<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const INVOICE_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'collect'];

/**
 * Issue a document through the API; returns [proforma request, resource data].
 *
 * @return array{0: ProformaRequest, 1: array<string, mixed>}
 */
function issuedInvoice(): array
{
    $request = invoiceRequest();
    $data = test()->postJson("/api/proforma-requests/{$request->id}/invoice", invoicePayload($request))->assertCreated()->json('data');

    return [$request, $data];
}

it('AC-005: details switch the type, edits recompute, collections lock edit/delete and clearing them frees the delete', function () {
    Sanctum::actingAs(invoiceUserWith(INVOICE_ABILITIES));
    [$request, $invoice] = issuedInvoice();
    $url = "/api/invoices/{$invoice['id']}";

    $this->patchJson("{$url}/details", ['external_number' => 'FT-9', 'external_date' => '2026-04-01', 'tag' => 'final', 'deviation' => '5.5'])
        ->assertOk()->assertJsonPath('data.type', 'invoice')->assertJsonPath('data.tag', 'final')->assertJsonPath('data.deviation', '5.50');
    $this->patchJson("{$url}/details", ['external_number' => null])
        ->assertOk()->assertJsonPath('data.type', 'proforma')->assertJsonPath('data.external_date', null);
    $this->patchJson("{$url}/details", ['external_number' => 'FT-9'])->assertUnprocessable()->assertJsonValidationErrors('external_date');

    $payload = invoicePayload($request, ['lines' => [[...invoicePayload($request)['lines'][0], 'quantity' => '10', 'unit_price' => '100']]]);
    $this->putJson($url, $payload)->assertOk()->assertJsonPath('data.total_amount', '1220.00')
        ->assertJsonPath('data.number', $invoice['number'])->assertJsonCount(2, 'data.installments');
    $this->putJson($url, [...$payload, 'company_id' => Company::factory()->create()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('company_id');

    $installment = $this->getJson($url)->assertOk()->json('data.installments.0');
    $collection = "/api/invoice-installments/{$installment['id']}/collection";
    $this->putJson($collection, ['collected_amount' => '9999', 'collected_at' => '2026-04-02'])->assertUnprocessable();
    $this->putJson($collection, ['collected_amount' => '100', 'collected_at' => '2026-04-02'])
        ->assertOk()->assertJsonPath('data.has_collections', true)->assertJsonPath('data.collected_amount', '100.00')
        ->assertJsonPath('data.residual_amount', '1120.00')->assertJsonPath('data.installments.0.status', 'partially_paid');

    $this->putJson($url, $payload)->assertStatus(409)->assertJsonPath('message', 'This invoice has collected installments and cannot be modified.');
    $this->deleteJson($url)->assertStatus(409)->assertJsonPath('message', 'This invoice has collected installments and cannot be deleted.');

    $this->deleteJson($collection)->assertOk()->assertJsonPath('data.has_collections', false)->assertJsonPath('data.installments.0.status', 'unpaid');
    $this->deleteJson($url)->assertNoContent();
    $request->refresh();
    expect(Invoice::query()->count())->toBe(0)->and($request->status->value)->toBe('pending')->and($request->issued_at)->toBeNull();
});

it('AC-006: every endpoint answers 403 without its ability; preview needs create or update; summary returns 12 months', function () {
    // Users first: Sanctum::actingAs switches the default guard, which findOrCreate of the permissions would follow.
    $admin = invoiceUserWith(INVOICE_ABILITIES);
    $without = collect(INVOICE_ABILITIES)->mapWithKeys(fn (string $ability): array => [$ability => invoiceUserWith(array_values(array_diff(INVOICE_ABILITIES, [$ability])))]);
    $viewer = invoiceUserWith(['view']);
    $updater = invoiceUserWith(['update']);
    $lister = invoiceUserWith(['viewAny']);
    Sanctum::actingAs($admin);
    [, $invoice] = issuedInvoice();
    $pending = invoiceRequest();
    $installmentId = $invoice['installments'][0]['id'];
    $collection = ['collected_amount' => '1', 'collected_at' => '2026-04-02'];
    $preview = ['document_date' => '2026-03-15', 'payment_method_id' => $pending->payment_method_id, 'net_amount' => '10', 'vat_amount' => '2', 'total_amount' => '12'];
    $write = invoicePayload($pending);

    $matrix = [
        ['create', 'getJson', "/api/proforma-requests/{$pending->id}/invoice-draft", []],
        ['create', 'postJson', "/api/proforma-requests/{$pending->id}/invoice", $write],
        ['view', 'getJson', "/api/invoices/{$invoice['id']}", []],
        ['update', 'putJson', "/api/invoices/{$invoice['id']}", $write],
        ['update', 'patchJson', "/api/invoices/{$invoice['id']}/details", ['tag' => 'estimate']],
        ['delete', 'deleteJson', "/api/invoices/{$invoice['id']}", []],
        ['collect', 'putJson', "/api/invoice-installments/{$installmentId}/collection", $collection],
        ['collect', 'deleteJson', "/api/invoice-installments/{$installmentId}/collection", []],
        ['viewAny', 'getJson', '/api/invoices/monthly-summary?year=2026', []],
    ];

    foreach ($matrix as [$missing, $method, $uri, $body]) {
        Sanctum::actingAs($without[$missing]);
        $this->{$method}($uri, $body)->assertForbidden();
    }

    Sanctum::actingAs($viewer);
    $this->postJson('/api/invoices/installment-preview', $preview)->assertForbidden();
    Sanctum::actingAs($updater);
    $this->postJson('/api/invoices/installment-preview', $preview)->assertOk()->assertJsonCount(2, 'data');

    Sanctum::actingAs($lister);
    $months = $this->getJson('/api/invoices/monthly-summary?year=2026&type=proforma')->assertOk()->json('data.months');
    expect($months)->toHaveCount(12)->and($months[2])->toBe(['month' => 3, 'count' => 1, 'total_amount' => '36.97']);
    $this->getJson('/api/invoices/monthly-summary?year=1999')->assertUnprocessable();
});
