<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Invoice;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// spec 0195 AC-003 — GET /api/invoices/{invoice}/pdf.

uses(RefreshDatabase::class);

it('AC-003: 200 application/pdf with the default layout and a typed filename', function (): void {
    DocumentLayout::factory()->create(['module' => 'invoices', 'is_default' => true, 'config' => DocumentLayoutFactory::minimalConfig()]);
    $invoice = Invoice::factory()->create(['number' => 7, 'year' => 2026]);
    $convertedDocx = captureDocxToPdfConversion();
    Sanctum::actingAs(invoiceUserWith(['view']));

    $response = $this->get("/api/invoices/{$invoice->id}/pdf")->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('proforma_7_2026.pdf')
        ->and($response->streamedContent())->toStartWith('%PDF-')
        ->and($convertedDocx())->toStartWith('PK');
});

it('AC-003: an explicit active invoices layout_id is used', function (): void {
    $layout = DocumentLayout::factory()->create(['module' => 'invoices', 'config' => DocumentLayoutFactory::minimalConfig()]);
    $invoice = Invoice::factory()->asInvoice()->create(['number' => 3, 'year' => 2026]);
    captureDocxToPdfConversion();
    Sanctum::actingAs(invoiceUserWith(['view']));

    $this->get("/api/invoices/{$invoice->id}/pdf?layout_id={$layout->id}")
        ->assertOk()
        ->assertDownload('invoice_3_2026.pdf');
});

it('AC-003: 422 with the contract message when no layout is available', function (): void {
    $invoice = Invoice::factory()->create();
    Sanctum::actingAs(invoiceUserWith(['view']));

    $this->getJson("/api/invoices/{$invoice->id}/pdf")
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'No document layout available for invoices.');
});

it('AC-003: 422 for a layout_id of another module or inactive', function (): void {
    $quotes = DocumentLayout::factory()->create(['module' => 'quotes']);
    $inactive = DocumentLayout::factory()->create(['module' => 'invoices', 'is_active' => false]);
    $invoice = Invoice::factory()->create();
    Sanctum::actingAs(invoiceUserWith(['view']));

    $this->getJson("/api/invoices/{$invoice->id}/pdf?layout_id={$quotes->id}")->assertUnprocessable()->assertJsonValidationErrors('layout_id');
    $this->getJson("/api/invoices/{$invoice->id}/pdf?layout_id={$inactive->id}")->assertUnprocessable()->assertJsonValidationErrors('layout_id');
});

it('AC-003: 403 without invoices.view', function (): void {
    DocumentLayout::factory()->create(['module' => 'invoices', 'is_default' => true]);
    $invoice = Invoice::factory()->create();
    Sanctum::actingAs(invoiceUserWith([]));

    $this->getJson("/api/invoices/{$invoice->id}/pdf?layout_id=999999")->assertForbidden();
    $this->getJson("/api/invoices/{$invoice->id}/pdf")->assertForbidden();

});

it('AC-003: 404 on an unknown invoice', function (): void {
    Sanctum::actingAs(invoiceUserWith(['view']));

    $this->getJson('/api/invoices/999999/pdf')->assertNotFound();
});
