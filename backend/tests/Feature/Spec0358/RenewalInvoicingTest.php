<?php

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0358 MT-03: a line is invoiced once per commessa (D-7, AC-016/AC-017).
 */
uses(RefreshDatabase::class);

/**
 * The same line in COM-A (period 0) and COM-B (period 1), one request each.
 *
 * @return array{0: ProformaRequest, 1: ProformaRequest, 2: int} [request A, request B, line id]
 */
function sameLineInTwoPeriods(): array
{
    $method = PaymentMethod::factory()->create(['installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);
    $a = proformaWorkOrder([['consultancy', null]], $method);
    $a->quote->update(['company_id' => Company::factory()->create()->id]);
    $line = $a->quoteLines()->first();
    $b = WorkOrder::factory()->create(['quote_id' => $a->quote_id]);
    attachLineInPeriod($b, $line, 1);

    return [invoiceRequest('consultancy', null, $a), invoiceRequest('consultancy', null, $b), $line->id];
}

it('AC-016: the line invoiced in COM-A is invoiced again in COM-B, never twice in the same commessa', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create']));
    [$requestA, $requestB] = sameLineInTwoPeriods();
    $requestB2 = invoiceRequest('consultancy', null, $requestB->workOrder);

    $this->postJson("/api/proforma-requests/{$requestA->id}/invoice", invoicePayload($requestA))->assertCreated();
    $this->postJson("/api/proforma-requests/{$requestB->id}/invoice", invoicePayload($requestB))->assertCreated();

    $this->postJson("/api/proforma-requests/{$requestB2->id}/invoice", invoicePayload($requestB2))
        ->assertUnprocessable()->assertJsonPath('errors', ['lines.0.quote_line_id' => [__('invoices.line_already_invoiced')]]);
});

it('AC-017: the draft of the COM-B request offers the line invoiced only in COM-A', function () {
    Sanctum::actingAs(invoiceUserWith(['viewAny', 'view', 'create']));
    [$requestA, $requestB, $lineId] = sameLineInTwoPeriods();
    $offered = fn (ProformaRequest $request): array => collect($this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()->json('data.available_lines'))->pluck('quote_line_id')->all();

    $this->postJson("/api/proforma-requests/{$requestA->id}/invoice", invoicePayload($requestA))->assertCreated();

    expect($offered($requestB))->toBe([$lineId]);
});
