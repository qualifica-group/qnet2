<?php

use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `QuoteScopedTableDefinition` (spec 0095, D-8): scopes the `work-orders`
 * domain's rows/values/columns endpoints to a single Quote via the
 * `quoteId`/`quote_id` request parameter — the Contratto detail's
 * "Commesse" tab. AC-050..054. Ricalca 1:1
 * tests/Feature/Quotes/QuoteOpportunityScopeTest.php (spec 0067).
 *
 * `workOrderUserWith()` is declared (guarded) in WorkOrderCrudTest.php, same
 * directory, reused here as-is.
 */
uses(RefreshDatabase::class);

it('AC-050: rows scoped to quote A returns exactly A\'s work orders, and pagination.total matches', function () {
    $actor = workOrderUserWith(['viewAny']);
    $quoteA = Quote::factory()->create();
    $quoteB = Quote::factory()->create();
    WorkOrder::factory()->count(3)->create(['quote_id' => $quoteA->id]);
    WorkOrder::factory()->count(2)->create(['quote_id' => $quoteB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => $quoteA->id,
    ])->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(3);
});

it('AC-053/AC-054: omitting quoteId returns every work order, unchanged from today (non-regression)', function () {
    $actor = workOrderUserWith(['viewAny']);
    $quoteA = Quote::factory()->create();
    $quoteB = Quote::factory()->create();
    WorkOrder::factory()->count(3)->create(['quote_id' => $quoteA->id]);
    WorkOrder::factory()->count(2)->create(['quote_id' => $quoteB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect($response->json('pagination.total'))->toBe(5);
});

it('quoteId non-numeric or nonexistent -> 422; null -> accepted as absent', function () {
    $actor = workOrderUserWith(['viewAny']);
    WorkOrder::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => 'not-a-number',
    ])->assertStatus(422)->assertJsonValidationErrors('quoteId');

    $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('quoteId');

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => null,
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

it('a user without work-orders.viewAny is denied even with quoteId set', function () {
    $actor = workOrderUserWith([]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => $quote->id,
    ])->assertForbidden();
});

it('AC-051: GET columns response is byte-identical with and without quote_id (no second table definition)', function () {
    $actor = workOrderUserWith(['viewAny']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $unscoped = $this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data');
    $scoped = $this->getJson('/api/tables/work-orders/columns?quote_id='.$quote->id)->assertOk()->json('data');

    expect($scoped)->toBe($unscoped);
});

it('AC-052: per-row actions in a scoped grid still come from WorkOrderPolicy, never widened', function () {
    $actor = workOrderUserWith(['viewAny', 'view', 'update']);
    $quote = Quote::factory()->create();
    WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => $quote->id,
    ])->assertOk();

    $row = $response->json('items.0');
    expect($row['actions'])->toBe(['view'])
        ->and($row['actions'])->not->toContain('delete');
});

it('quoteId is a no-op on the non-scoped users domain', function () {
    $userActor = User::factory()->create();
    Permission::findOrCreate('users.viewAny');
    $userActor->givePermissionTo('users.viewAny');
    User::factory()->count(2)->create();
    Sanctum::actingAs($userActor);

    $existingQuoteId = Quote::factory()->create()->id;

    $unscoped = $this->postJson('/api/tables/users/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('pagination.total');
    $withQuoteId = $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25, 'quoteId' => $existingQuoteId,
    ])->assertOk()->json('pagination.total');

    expect($withQuoteId)->toBe($unscoped);
});
