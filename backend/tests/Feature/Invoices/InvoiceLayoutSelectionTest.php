<?php

declare(strict_types=1);

use App\Models\DocumentLayout;
use App\Models\Invoice;
use App\Models\User;
use Database\Factories\DocumentLayoutFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0196 AC-001..AC-004 — layout saved on the invoice.

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function selectionLayout(string $marker, array $attributes = []): DocumentLayout
{
    $block = [
        'id' => 'b1', 'type' => 'text', 'align' => 'left', 'space_before' => 0, 'space_after' => 0, 'line_height' => 1.0,
        'runs' => [['text' => $marker, 'field' => null, 'bold' => false, 'italic' => false, 'underline' => false, 'font' => null, 'size' => null, 'color' => null]],
    ];
    $config = array_replace(DocumentLayoutFactory::minimalConfig(), ['body' => ['blocks' => [$block]]]);

    return DocumentLayout::factory()->create($attributes + ['module' => 'invoices', 'config' => $config]);
}

function selectionDocxText(string $docx): string
{
    $path = tempnam(sys_get_temp_dir(), 'invsel').'.docx';
    file_put_contents($path, $docx);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return html_entity_decode(strip_tags($xml));
}

it('AC-001: POST/PUT save an active invoices layout, refuse quotes/inactive ones; PATCH details updates it even with a collection', function (): void {
    Sanctum::actingAs(invoiceUserWith(['create', 'update', 'view', 'collect']));
    $request = invoiceRequest();
    $layout = selectionLayout('A');

    $created = $this->postJson("/api/proforma-requests/{$request->id}/invoice", invoicePayload($request, ['layout_id' => $layout->id]))
        ->assertCreated()->assertJsonPath('data.layout', ['id' => $layout->id, 'name' => $layout->name])->json('data');
    $url = "/api/invoices/{$created['id']}";
    $payload = invoicePayload($request, ['company_id' => $created['company']['id']]);

    $this->putJson($url, [...$payload, 'layout_id' => DocumentLayout::factory()->create(['module' => 'quotes'])->id])->assertUnprocessable()->assertJsonValidationErrors('layout_id');
    $this->putJson($url, [...$payload, 'layout_id' => selectionLayout('I', ['is_active' => false])->id])->assertUnprocessable()->assertJsonValidationErrors('layout_id');
    $this->putJson($url, $payload)->assertOk()->assertJsonPath('data.layout', null);

    $other = selectionLayout('B');
    $installmentId = $this->getJson($url)->assertOk()->json('data.installments.0.id');
    $this->putJson("/api/invoice-installments/{$installmentId}/collection", ['collected_amount' => '1.00', 'collected_at' => '2026-04-01', 'residual_mode' => 'spread'])->assertOk();
    $this->patchJson("{$url}/details", ['layout_id' => $other->id])->assertOk()->assertJsonPath('data.layout.id', $other->id);
    $this->patchJson("{$url}/details", ['tag' => 'final'])->assertOk()->assertJsonPath('data.layout.id', $other->id);
    $this->patchJson("{$url}/details", ['layout_id' => null])->assertOk()->assertJsonPath('data.layout', null);
});

it('AC-001: the draft exposes layout null', function (): void {
    Sanctum::actingAs(invoiceUserWith(['create']));
    $request = invoiceRequest();

    $this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()->assertJsonPath('data.defaults.layout', null);
});

it('AC-002: the pdf uses the saved layout (even deactivated), else the default; the query layout_id wins', function (): void {
    $default = selectionLayout('MARK-DEFAULT', ['is_default' => true]);
    $saved = selectionLayout('MARK-SAVED');
    $override = selectionLayout('MARK-OVERRIDE');
    $withLayout = Invoice::factory()->create(['layout_id' => $saved->id]);
    $without = Invoice::factory()->create();
    $docx = captureDocxToPdfConversion();
    Sanctum::actingAs(invoiceUserWith(['view']));

    $this->get("/api/invoices/{$without->id}/pdf")->assertOk();
    expect(selectionDocxText($docx()))->toContain('MARK-DEFAULT');

    $this->get("/api/invoices/{$withLayout->id}/pdf")->assertOk();
    expect(selectionDocxText($docx()))->toContain('MARK-SAVED');

    $saved->update(['is_active' => false]);
    $this->get("/api/invoices/{$withLayout->id}/pdf")->assertOk();
    expect(selectionDocxText($docx()))->toContain('MARK-SAVED')->not->toContain('MARK-DEFAULT');

    $this->get("/api/invoices/{$withLayout->id}/pdf?layout_id={$override->id}")->assertOk();
    expect(selectionDocxText($docx()))->toContain('MARK-OVERRIDE')->and($default->is_default)->toBeTrue();
});

it('AC-003: the invoice_pdf email attachment uses the saved layout', function (): void {
    selectionLayout('MARK-DEFAULT', ['is_default' => true]);
    $invoice = Invoice::factory()->create(['layout_id' => selectionLayout('MARK-SAVED')->id]);
    $docx = captureDocxToPdfConversion();
    foreach (['viewAny', 'view', 'viewEmails', 'sendEmail'] as $ability) {
        Permission::findOrCreate("invoices.{$ability}");
    }
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo(['invoices.view', 'invoices.viewEmails', 'invoices.sendEmail']));

    $id = $this->postJson("/api/invoices/{$invoice->id}/emails", ['attach_pdf' => false])->assertCreated()->json('data.id');
    $this->postJson("/api/invoices/{$invoice->id}/emails/{$id}/attachments/import", ['source' => 'invoice_pdf'])->assertCreated();

    expect(selectionDocxText($docx()))->toContain('MARK-SAVED')->not->toContain('MARK-DEFAULT');
});

it('AC-004: deleting a layout used by an invoice is 422 and the layout stays', function (): void {
    foreach (['viewAny', 'view', 'delete'] as $ability) {
        Permission::findOrCreate("document-layouts.{$ability}");
    }
    Sanctum::actingAs(tap(User::factory()->create())->givePermissionTo(['document-layouts.view', 'document-layouts.delete']));
    $layout = selectionLayout('USED');
    Invoice::factory()->count(2)->create(['layout_id' => $layout->id]);

    $this->deleteJson("/api/document-layouts/{$layout->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invoices');

    expect(DocumentLayout::query()->whereKey($layout->id)->exists())->toBeTrue();
});
