<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Two pending consultancy requests on one work order offering two lines.
 *
 * @return array{0: ProformaRequest, 1: ProformaRequest, 2: array<int, int>} [first, second, quote line ids]
 */
function twoRequestsOnOneWorkOrder(): array
{
    $method = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);
    $workOrder = proformaWorkOrder([['consultancy', null], ['consultancy', null]], $method);
    $workOrder->quote->update(['company_id' => Company::factory()->create()->id]);

    return [
        invoiceRequest('consultancy', null, $workOrder),
        invoiceRequest('consultancy', null, $workOrder),
        $workOrder->quoteLines()->orderBy('quote_lines.id')->pluck('quote_lines.id')->all(),
    ];
}

it('AC-010: a quote line already invoiced is not offered by the draft of another request', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create']));
    [$first, $second, $lineIds] = twoRequestsOnOneWorkOrder();
    $draft = fn () => collect($this->getJson("/api/proforma-requests/{$second->id}/invoice-draft")->assertOk()->json('data.available_lines'))->pluck('quote_line_id')->all();

    expect($draft())->toEqualCanonicalizing($lineIds);

    $this->postJson("/api/proforma-requests/{$first->id}/invoice", invoicePayload($first))->assertCreated();

    expect($draft())->toBe([$lineIds[1]]);
});

it('AC-011: POST and PUT of another document refuse an already invoiced line; the owner may keep it', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create', 'update']));
    [$first, $second, $lineIds] = twoRequestsOnOneWorkOrder();
    $firstInvoice = $this->postJson("/api/proforma-requests/{$first->id}/invoice", invoicePayload($first))->assertCreated()->json('data');
    $taken = invoicePayload($second, ['lines' => [[...invoicePayload($second)['lines'][0], 'quote_line_id' => $lineIds[0]]]]);

    $this->postJson("/api/proforma-requests/{$second->id}/invoice", $taken)
        ->assertUnprocessable()->assertJsonPath('errors', ['lines.0.quote_line_id' => [__('invoices.line_already_invoiced')]]);
    expect(Invoice::query()->count())->toBe(1);

    $free = invoicePayload($second, ['lines' => [[...invoicePayload($second)['lines'][0], 'quote_line_id' => $lineIds[1]]]]);
    $secondInvoice = $this->postJson("/api/proforma-requests/{$second->id}/invoice", $free)->assertCreated()->json('data');

    $this->putJson("/api/invoices/{$secondInvoice['id']}", $taken)
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quote_line_id');
    expect(InvoiceLine::query()->where('invoice_id', $secondInvoice['id'])->value('quote_line_id'))->toBe($lineIds[1]);

    $this->putJson("/api/invoices/{$firstInvoice['id']}", invoiceWithUnitPrice(invoicePayload($first), '20.00'))->assertOk();
});
