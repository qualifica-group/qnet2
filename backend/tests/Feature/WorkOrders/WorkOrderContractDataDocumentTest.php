<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0403, AC-001: each line of the contract data carries the most recent
 * document of THIS commessa that bills it, with its computed payment status.
 */
uses(RefreshDatabase::class);

function documentBilling(WorkOrder $workOrder, QuoteLine $line, array $invoice = [], ?string $dueDate = null): Invoice
{
    $document = Invoice::factory()->create(['work_order_id' => $workOrder->id, ...$invoice]);
    InvoiceLine::factory()->create(['invoice_id' => $document->id, 'quote_line_id' => $line->id]);

    if ($dueDate !== null) {
        InvoiceInstallment::factory()->create(['invoice_id' => $document->id, 'sequence' => 1, 'due_date' => $dueDate]);
    }

    return $document;
}

function contractDataLines(WorkOrder $workOrder): array
{
    Sanctum::actingAs(workOrderPaymentsUserWith(['viewContractData']));

    return collect(test()->getJson("/api/work-orders/{$workOrder->id}/contract-data")->assertOk()->json('data.lines'))->keyBy('quote_line_id')->all();
}

it('AC-001: a line billed by a proforma with a long overdue installment shows it, an unbilled line shows null', function () {
    $workOrder = WorkOrder::factory()->create();
    $billed = contractDataLine($workOrder, 'consultancy', 100);
    $unbilled = contractDataLine($workOrder, 'institution', 200);
    $proforma = documentBilling($workOrder, $billed, [], now()->subDays(30)->toDateString());

    $lines = contractDataLines($workOrder);

    expect($lines[$billed->id]['document'])->toBe([
        'id' => $proforma->id,
        'type' => 'proforma',
        'number_label' => "{$proforma->number}/{$proforma->year}",
        'payment_status' => 'seriously_overdue',
    ])->and($lines[$unbilled->id]['document'])->toBeNull();
});

it('the newest document wins by date then id', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 100);
    documentBilling($workOrder, $line, ['document_date' => '2026-09-01']);
    $newest = documentBilling($workOrder, $line, ['document_date' => '2026-10-01']);
    documentBilling($workOrder, $line, ['document_date' => '2026-08-01']);

    expect(contractDataLines($workOrder)[$line->id]['document']['id'])->toBe($newest->id);
});

it('ignores a document of another commessa', function () {
    $workOrder = WorkOrder::factory()->create();
    $line = contractDataLine($workOrder, 'consultancy', 100);
    documentBilling(WorkOrder::factory()->create(), $line);

    expect(contractDataLines($workOrder)[$line->id]['document'])->toBeNull();
});
