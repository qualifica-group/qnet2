<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Company;
use App\Models\ProductTypology;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\VatRate;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0288: the institution draft bills the supplier commission on the lines
 * of every offer of the same company (D-3/D-4); the save-time guard keeps every
 * document to the lines of its request.
 */
uses(RefreshDatabase::class);

function institutionDraftRequest(WorkOrder $workOrder, Quote $quote, string $kind, ?Registry $supplier): ProformaRequest
{
    return ProformaRequest::factory()->create([
        'work_order_id' => $workOrder->id,
        'quote_id' => $quote->id,
        'kind' => $kind,
        'supplier_id' => $supplier?->id,
        'payment_method_id' => $quote->payment_method_id,
    ]);
}

function institutionDraftPayload(Quote $quote, array $quoteLineIds): array
{
    $vat = VatRate::factory()->create(['rate' => '22.00']);

    return [
        'document_date' => '2026-03-15',
        'company_id' => $quote->company_id,
        'customer_registry_id' => Registry::factory()->create()->id,
        'payment_method_id' => $quote->payment_method_id,
        'lines' => array_map(fn (int $id): array => [
            'quote_line_id' => $id, 'description' => 'Commission', 'quantity' => '1', 'unit_price' => '100.00', 'vat_rate_id' => $vat->id,
        ], $quoteLineIds),
    ];
}

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->supplier = Registry::factory()->create();
    $this->workOrder = WorkOrder::factory()->multiQuote()->create();
    $this->first = institutionOffer($this->company, 'QUO-0001');
    $this->second = institutionOffer($this->company, 'QUO-0002');
    $this->vat = VatRate::factory()->create(['rate' => '22.00']);
    $sold = ['quantity' => 2, 'unit_price' => 500, 'net_amount' => 1000, 'vat_rate_id' => $this->vat->id];
    $this->lineA = institutionLine($this->workOrder, $this->first, $this->supplier, 'institution', $sold);
    $this->lineB = institutionLine($this->workOrder, $this->second, $this->supplier, 'institution', $sold);
    $this->consultancy = institutionLine($this->workOrder, $this->first, null, 'consultancy');
    $this->otherSupplier = institutionLine($this->workOrder, $this->first, Registry::factory()->create());
    $this->otherCompany = institutionLine($this->workOrder, institutionOffer(Company::factory()->create(), 'QUO-0000'), $this->supplier);
    $this->request = institutionDraftRequest($this->workOrder, $this->first, 'institution', $this->supplier);
});

function draftLines(ProformaRequest $request): array
{
    return test()->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()->json('data.available_lines');
}

function supplierPercentage(Registry $supplier, QuoteLine $line, float $value): void
{
    CommissionConfiguration::factory()
        ->forRecipient(CommissionRecipientRole::Supplier->recipientType(), $supplier->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Supplier, 'product_id' => $line->product_id, 'value' => $value, 'valid_from' => '2026-01-01']);
}

it('AC-004: the institution draft lists the supplier lines of every offer of the same company, nothing else', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));

    expect(array_column(draftLines($this->request), 'quote_line_id'))->toEqualCanonicalizing([$this->lineA->id, $this->lineB->id]);
});

it('AC-005: a percentage rule bills one unit at that share of the sale net amount, with VAT on it', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    supplierPercentage($this->supplier, $this->lineA, 10);

    $line = collect(draftLines($this->request))->firstWhere('quote_line_id', $this->lineA->id);

    expect($line['quantity'])->toBe('1.00')
        ->and($line['unit_price'])->toBe('100.00')
        ->and($line['net_amount'])->toBe('100.00')
        ->and($line['vat_amount'])->toBe('22.00')
        ->and($line['total_amount'])->toBe('122.00')
        ->and($line['commission']['type'])->toBe('PERCENTAGE')
        ->and((float) $line['commission']['value'])->toBe(10.0)
        ->and($line['commission']['base_amount'])->toBe('1000.00');
});

it('AC-008: without a rule the line bills zero; a typology with the supplier switch off still applies the rule', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    ProductTypology::query()->where('code', 'institution')->update(['supplier_commission_enabled' => false]);
    supplierPercentage($this->supplier, $this->lineA, 10);

    $lines = collect(draftLines($this->request));

    expect($lines->firstWhere('quote_line_id', $this->lineA->id)['net_amount'])->toBe('100.00')
        ->and($lines->firstWhere('quote_line_id', $this->lineB->id)['net_amount'])->toBe('0.00')
        ->and($lines->firstWhere('quote_line_id', $this->lineB->id)['commission']['type'])->toBeNull();
});

it('AC-009: the consultancy draft keeps the sale amounts and carries no commission', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $request = institutionDraftRequest($this->workOrder, $this->first, 'consultancy', null);

    $lines = draftLines($request);

    expect(array_column($lines, 'quote_line_id'))->toBe([$this->consultancy->id])
        ->and($lines[0]['commission'])->toBeNull()
        ->and($lines[0]['net_amount'])->toBe((string) $this->consultancy->net_amount);
});

it('AC-010: issuing refuses a line of another kind, supplier or company, and creates nothing', function (string $intruder) {
    Sanctum::actingAs(invoiceUserWith(['create']));

    $this->postJson("/api/proforma-requests/{$this->request->id}/invoice", institutionDraftPayload($this->first, [$this->lineA->id, $this->{$intruder}->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.1.quote_line_id']);

    expect($this->request->fresh()->invoice)->toBeNull();
})->with(['consultancy', 'otherSupplier', 'otherCompany']);

it('AC-010: a consultancy document refuses an institution line', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $request = institutionDraftRequest($this->workOrder, $this->first, 'consultancy', null);

    $this->postJson("/api/proforma-requests/{$request->id}/invoice", institutionDraftPayload($this->first, [$this->lineA->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.quote_line_id']);
});

it('AC-011: updating an issued document keeps its own lines and refuses a line outside the request', function () {
    Sanctum::actingAs(invoiceUserWith(['create', 'update']));
    $invoiceId = $this->postJson("/api/proforma-requests/{$this->request->id}/invoice", institutionDraftPayload($this->first, [$this->lineA->id, $this->lineB->id]))
        ->assertCreated()->json('data.id');

    $this->putJson("/api/invoices/{$invoiceId}", institutionDraftPayload($this->first, [$this->lineA->id, $this->lineB->id]))->assertOk();
    $this->putJson("/api/invoices/{$invoiceId}", institutionDraftPayload($this->first, [$this->lineA->id, $this->otherSupplier->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.1.quote_line_id']);
});
