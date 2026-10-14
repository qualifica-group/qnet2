<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Passive documents bill purchase order lines by quantity (spec 0254, D-2):
 * proforma and invoice alike up to the ordered quantity not yet billed; an
 * invoice receives what it bills (D-12, see PurchaseInvoiceAutoReceiptTest).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create', 'update', 'delete']));
});

/** @return array{0: array<string, mixed>, 1: PurchaseOrderLine} a payload and an order line of 10 units at 5.00 with `$received` received */
function quantityScenario(string $received = '0', PurchaseOrderStatus $status = PurchaseOrderStatus::Approved): array
{
    $payload = purchaseInvoicePayload();
    $order = invoiceableOrderOf($payload, $status, 1);
    $line = $order->lines[0];
    $line->update(['quantity' => '10.000', 'unit_price' => '5.00']);

    if ($received !== '0') {
        purchaseOrderReceive($order, [$line->id => $received]);
    }

    return [$payload, $line->fresh()];
}

/** @return array<string, mixed> */
function quantityLine(PurchaseOrderLine $line, string $quantity): array
{
    return [...orderLinePayload($line), 'quantity' => $quantity, 'unit_price' => '5.00'];
}

it('AC-007: a proforma may bill the ordered quantity before any receipt, not more', function () {
    [$payload, $line] = quantityScenario();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '10.001')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '10'])]]);

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '10')]])->assertCreated();
});

it('AC-007: an invoice may bill up to the ordered quantity not yet billed, beyond the received one', function () {
    [$payload, $line] = quantityScenario('4');
    $invoice = [...$payload, 'document_type' => 'invoice', 'document_number' => 'FT-1'];

    $this->postJson('/api/purchase-invoices', [...$invoice, 'lines' => [quantityLine($line, '10.5')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '10'])]]);

    $this->postJson('/api/purchase-invoices', [...$invoice, 'lines' => [quantityLine($line, '7')]])->assertCreated();
    $this->postJson('/api/purchase-invoices', [...$invoice, 'document_number' => 'FT-2', 'lines' => [quantityLine($line, '3.5')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '3'])]]);
    $this->postJson('/api/purchase-invoices', [...$invoice, 'document_number' => 'FT-3', 'lines' => [quantityLine($line, '3')]])->assertCreated();
});

it('AC-007: a closed residual lowers the ceiling to the received quantity for proforma and invoice alike', function () {
    [$payload, $line] = quantityScenario('4');
    $line->forceFill(['residual_closed_at' => now()])->save();

    foreach ([['proforma', null], ['invoice', 'FT-1']] as [$type, $number]) {
        $this->postJson('/api/purchase-invoices', [...$payload, 'document_type' => $type, 'document_number' => $number, 'lines' => [quantityLine($line, '4.5')]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '4'])]]);
    }
});

it('AC-007: two documents that together exceed the ceiling are refused on the second', function () {
    [$payload, $line] = quantityScenario();

    $first = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6')]])->assertCreated()->json('data.id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '5')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '4'])]]);
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '4')]])->assertCreated();

    // Editing the first document: its own 6 do not count against itself, the 4 of the other do.
    $document = PurchaseInvoice::query()->findOrFail($first);
    $this->putJson("/api/purchase-invoices/{$first}", purchaseInvoiceUpdatePayload($document, ['lines' => [quantityLine($line, '6')]]))->assertOk();
    $this->putJson("/api/purchase-invoices/{$first}", purchaseInvoiceUpdatePayload($document->refresh(), ['lines' => [quantityLine($line, '6.5')]]))
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity');
});

it('AC-007: the rows of one document on the same order line are judged on their sum, the error sits on the first row', function () {
    [$payload, $line] = quantityScenario();

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6'), quantityLine($line, '5')]])
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quantity')->assertJsonMissingValidationErrors('lines.1.quantity');

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6'), quantityLine($line, '4')]])->assertCreated();

    expect(PurchaseInvoiceLine::query()->where('purchase_order_line_id', $line->id)->count())->toBe(2);
});

it('AC-008: a proforma beyond the received quantity becomes an invoice and receives the difference', function () {
    [$payload, $line] = quantityScenario('4');
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6')]])->assertCreated()->json('data.id');

    $this->patchJson("/api/purchase-invoices/{$id}/details", ['document_number' => 'FT-9', 'notes' => 'x'])
        ->assertOk()->assertJsonPath('data.document_type', 'invoice');

    expect($line->fresh()->received_quantity)->toBe('6.000')
        ->and(PurchaseOrderReceipt::query()->where('purchase_invoice_id', $id)->count())->toBe(1);
});

it('AC-008: an order that cannot receive refuses the conversion, the document stays unchanged', function () {
    [$payload, $line] = quantityScenario();
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6')]])->assertCreated()->json('data.id');
    PurchaseOrder::query()->whereKey($line->purchase_order_id)->update(['status' => PurchaseOrderStatus::Cancelled]);

    $this->patchJson("/api/purchase-invoices/{$id}/details", ['document_number' => 'FT-9', 'notes' => 'x'])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['document_number' => [__('purchase_invoices.order_not_receivable')]]);

    $document = PurchaseInvoice::query()->findOrFail($id);

    expect($document->document_number)->toBeNull()
        ->and($document->notes)->toBeNull()
        ->and($document->document_type->value)->toBe('proforma')
        ->and(PurchaseOrderReceipt::query()->count())->toBe(0);
});

it('AC-008: lines already received convert without any receipt, also next to other proformas', function () {
    [$payload, $line] = quantityScenario('6');
    $id = $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '6')]])->assertCreated()->json('data.id');
    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '4')]])->assertCreated();
    $receipts = PurchaseOrderReceipt::query()->count();

    $this->patchJson("/api/purchase-invoices/{$id}/details", ['document_number' => 'FT-9'])
        ->assertOk()->assertJsonPath('data.document_type', 'invoice');

    expect(PurchaseOrderReceipt::query()->count())->toBe($receipts);
});

it('AC-009: the draft proposes the available quantity, recomputes the amounts and drops exhausted lines', function () {
    [, $line] = quantityScenario('6');
    $order = PurchaseOrder::query()->findOrFail($line->purchase_order_id);
    $vat = VatRate::factory()->create(['rate' => '22.00']);
    $line->update(['vat_rate_id' => $vat->id]);
    $draftUrl = '/api/purchase-invoices/draft?'.http_build_query(['purchase_order_ids' => [$order->id]]);

    $row = $this->getJson($draftUrl)->assertOk()->json('data.lines.0');

    expect($row)->toMatchArray(['quantity' => '10.000', 'available_quantity' => '10.000', 'net_amount' => '50.00', 'vat_amount' => '11.00', 'total_amount' => '61.00']);

    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '6.000']);

    $this->getJson($draftUrl)->assertOk()->assertJsonPath('data.lines.0.quantity', '4.000');

    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '4.000']);

    $this->getJson($draftUrl)->assertOk()->assertJsonPath('data.lines', [])->assertJsonPath('data.purchase_order_ids', [$order->id]);
});

it('AC-009: the invoiceable orders exclude the exhausted ones and ignore any document_type', function () {
    [$payload, $line] = quantityScenario();
    $order = PurchaseOrder::query()->findOrFail($line->purchase_order_id);
    $url = "/api/purchase-invoices/invoiceable-orders?supplier_id={$payload['supplier_id']}&company_id={$payload['company_id']}";

    $this->getJson($url)->assertOk()->assertJsonPath('data.0.id', $order->id);
    $this->getJson("{$url}&document_type=invoice")->assertOk()->assertJsonPath('data.0.id', $order->id);

    PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '10.000']);

    $this->getJson($url)->assertOk()->assertJsonPath('data', []);
});

it('AC-012: the guard locks the orders, then the order lines, then the document lines', function () {
    [$payload, $line] = quantityScenario();
    $tables = [];
    DB::listen(function ($query) use (&$tables): void {
        if (str_contains($query->sql, 'from "purchase_orders" where "id" in')) {
            $tables[] = 'orders';
        } elseif (str_contains($query->sql, '"received_quantity"') && str_contains($query->sql, 'from "purchase_order_lines"')) {
            $tables[] = 'order_lines';
        } elseif (str_contains($query->sql, 'from "purchase_invoice_lines" where "purchase_order_line_id" in')) {
            $tables[] = 'invoice_lines';
        }
    });

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '3')]])->assertCreated();

    expect(array_values(array_unique($tables)))->toBe(['orders', 'order_lines', 'invoice_lines']);
});

it('AC-012: a line committed by a competing document before the guard reads is seen by the re-check', function () {
    [$payload, $line] = quantityScenario();
    $raced = false;
    DB::listen(function ($query) use (&$raced, $line): void {
        if (! $raced && str_contains($query->sql, 'from "purchase_orders" where "id" in')) {
            $raced = true;
            PurchaseInvoiceLine::factory()->create(['purchase_order_line_id' => $line->id, 'quantity' => '8.000']);
        }
    });

    $this->postJson('/api/purchase-invoices', [...$payload, 'lines' => [quantityLine($line, '5')]])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['lines.0.quantity' => [__('purchase_invoices.line_over_available', ['available' => '2'])]]);

    expect($raced)->toBeTrue();
});
