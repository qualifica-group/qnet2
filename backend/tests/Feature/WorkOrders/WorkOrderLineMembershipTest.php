<?php

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Una riga, una sola Commessa" (spec 0095, D-4/D-5): AC-040 (DB-level
 * UNIQUE(quote_line_id)), AC-042 (a PATCH resending its own lines is not a
 * duplicate) and AC-043 (quote-offer-lines/for-select excludes programmed
 * lines, `except_work_order_id` readmits one work order's own). AC-041 (the
 * migration's own conflict-detection) lives in
 * QuoteLineWorkOrderMigrationTest.php, same directory.
 */
uses(RefreshDatabase::class);

it('AC-040: the pivot rejects the same quote_line_id attached to a SECOND work order, even bypassing the app', function () {
    $line = QuoteLine::factory()->create();
    $first = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    $second = WorkOrder::factory()->create(['quote_id' => $line->quote_id]);
    $first->quoteLines()->attach($line->id);

    expect(fn () => $second->quoteLines()->attach($line->id))->toThrow(QueryException::class);
});

it('AC-042: PATCH resending the lines the work order ALREADY owns is not treated as a conflict', function () {
    Permission::findOrCreate('work-orders.update');
    // `viewAll` lifts the membership scoping (user directive 2026-09-02):
    // this test is about line ownership, not about who may see a commessa.
    Permission::findOrCreate('work-orders.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.update', 'work-orders.viewAll']);

    $workOrder = WorkOrder::factory()->create();
    $lineA = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $workOrder->quoteLines()->attach([$lineA->id, $lineB->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'quote_line_ids' => [$lineA->id, $lineB->id],
    ])->assertOk();

    expect($workOrder->quoteLines()->pluck('quote_lines.id')->sort()->values()->all())
        ->toBe(collect([$lineA->id, $lineB->id])->sort()->values()->all());
});

it('AC-042 (cross-check): PATCH on ANOTHER work order with an already-owned line is still 422', function () {
    Permission::findOrCreate('work-orders.update');
    // `viewAll` lifts the membership scoping (user directive 2026-09-02):
    // this test is about line ownership, not about who may see a commessa.
    Permission::findOrCreate('work-orders.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.update', 'work-orders.viewAll']);

    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $owner = WorkOrder::factory()->create(['quote_id' => $quote->id, 'code' => 'COM-0007']);
    $owner->quoteLines()->attach($line->id);
    $other = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/work-orders/{$other->id}", ['quote_line_ids' => [$line->id]])
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect($response->json('errors.quote_line_ids.0'))->toContain('COM-0007');
});

it('AC-043: quote-offer-lines/for-select excludes a programmed line; except_work_order_id readmits its own', function () {
    Permission::findOrCreate('quotes.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('quotes.view');

    $quote = Quote::factory()->create();
    $free = QuoteLine::factory()->create(['quote_id' => $quote->id, 'sort_order' => 1]);
    $programmed = QuoteLine::factory()->create(['quote_id' => $quote->id, 'sort_order' => 2]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($programmed->id);
    Sanctum::actingAs($actor);

    $withoutException = $this->getJson("/api/quote-offer-lines/for-select?quote_id={$quote->id}")
        ->assertOk()->json('items');
    expect(collect($withoutException)->pluck('id')->all())->toBe([$free->id]);

    $withException = $this->getJson(
        "/api/quote-offer-lines/for-select?quote_id={$quote->id}&except_work_order_id={$workOrder->id}"
    )->assertOk()->json('items');
    expect(collect($withException)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$free->id, $programmed->id])->sort()->values()->all());
});
