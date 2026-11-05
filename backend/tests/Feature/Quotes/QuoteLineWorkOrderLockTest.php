<?php

use App\Models\QuoteLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0360, D-4: a REVENUE row assigned to a work order can be neither
 * removed nor changed (Offerte channel; the writer is shared by every channel).
 */
uses(RefreshDatabase::class);

it('AC-006: omitting a row assigned to a work order is 422 and keeps row and link', function () {
    ['quote' => $quote, 'lines' => [$locked, $free]] = inForceContract();
    assignLineToWorkOrder($locked, 'COM-0042');
    Sanctum::actingAs(quoteTableUserWith(['view', 'update']));

    $response = $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($free)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer_lines');

    expect($response->json('errors.offer_lines.0'))->toContain('COM-0042');
    $this->assertDatabaseHas('quote_lines', ['id' => $locked->id]);
    $this->assertDatabaseHas('quote_line_work_order', ['quote_line_id' => $locked->id]);
});

it('AC-007: changing any frozen field of an assigned row is 422, resending it identical is 200', function (array $change) {
    ['quote' => $quote, 'lines' => [$locked, $free]] = inForceContract();
    assignLineToWorkOrder($locked, 'COM-0042');
    Sanctum::actingAs(quoteTableUserWith(['view', 'update']));

    $response = $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($free), inForceRow($locked, $change)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('offer_lines.1');

    expect($response->json('errors')['offer_lines.1'][0])->toContain('COM-0042');
    expect((float) QuoteLine::find($locked->id)->quantity)->toBe(2.0);

    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($free), inForceRow($locked)]])->assertOk();
})->with([
    'quantity' => [['quantity' => 3]],
    'unit price' => [['unit_price' => 150.5]],
    'additional description' => [['additional_description' => 'changed']],
]);

it('AC-007: changing the product of an assigned row is 422', function () {
    ['quote' => $quote, 'lines' => [$locked]] = inForceContract(lineCount: 1);
    assignLineToWorkOrder($locked);
    Sanctum::actingAs(quoteTableUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($locked, ['product_id' => revenueLineProduct()->id])]])
        ->assertUnprocessable();
});

it('AC-007: sort order is not part of the lock', function () {
    ['quote' => $quote, 'lines' => [$locked, $free]] = inForceContract();
    assignLineToWorkOrder($locked);
    Sanctum::actingAs(quoteTableUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($free, ['sort_order' => 0]), inForceRow($locked, ['sort_order' => 1])]])
        ->assertOk();
});

it('AC-008: once the work order is deleted the row can be removed', function () {
    ['quote' => $quote, 'lines' => [$locked, $free]] = inForceContract();
    $workOrder = assignLineToWorkOrder($locked);
    $workOrder->delete();
    Sanctum::actingAs(quoteTableUserWith(['view', 'update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['offer_lines' => [inForceRow($free)]])->assertOk();

    $this->assertDatabaseMissing('quote_lines', ['id' => $locked->id]);
});

it('AC-012: the quote detail lists work_orders per row, empty when free', function () {
    ['quote' => $quote, 'lines' => [$locked, $free]] = inForceContract();
    $workOrder = assignLineToWorkOrder($locked, 'COM-0042');
    Sanctum::actingAs(quoteTableUserWith(['view']));

    $response = $this->getJson("/api/quotes/{$quote->id}")->assertOk();
    $byId = collect($response->json('data.offer_lines'))->keyBy('id');

    expect($byId[$locked->id]['work_orders'])->toBe([['id' => $workOrder->id, 'code' => 'COM-0042']])
        ->and($byId[$free->id]['work_orders'])->toBe([]);
});
