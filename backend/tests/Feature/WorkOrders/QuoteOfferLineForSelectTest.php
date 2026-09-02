<?php

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// GET /api/quote-offer-lines/for-select — AC-027, D-8
// ---------------------------------------------------------------------------

it('returns only REVENUE lines of the requested quote, ordered by sort_order, labelled product code + name (AC-027)', function () {
    Permission::findOrCreate('quotes.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('quotes.view');

    $quote = Quote::factory()->create();
    $otherQuote = Quote::factory()->create();

    $productA = Product::factory()->create(['name' => 'Consulenza ISO 9001']);
    $productB = Product::factory()->create(['name' => 'Formazione HACCP']);

    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productB->id, 'sort_order' => 2]);
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $productA->id, 'sort_order' => 1]);
    QuoteLine::factory()->cost()->create(['quote_id' => $quote->id, 'sort_order' => 3]);
    QuoteLine::factory()->create(['quote_id' => $otherQuote->id, 'sort_order' => 1]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/quote-offer-lines/for-select?quote_id={$quote->id}")->assertOk();
    $items = $response->json('items');

    expect($items)->toHaveCount(2)
        ->and(collect($items)->pluck('id')->all())->toBe([$lineA->id, $lineB->id])
        ->and($items[0]['label'])->toBe($productA->code.' — '.$productA->name)
        ->and($items[1]['label'])->toBe($productB->code.' — '.$productB->name);
});

it('422 when quote_id is missing (AC-027)', function () {
    Permission::findOrCreate('quotes.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('quotes.view');
    Sanctum::actingAs($actor);

    $this->getJson('/api/quote-offer-lines/for-select')->assertStatus(422)->assertJsonValidationErrors('quote_id');
});

it('422 when quote_id does not exist (AC-027)', function () {
    Permission::findOrCreate('quotes.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('quotes.view');
    Sanctum::actingAs($actor);

    $this->getJson('/api/quote-offer-lines/for-select?quote_id=999999')->assertStatus(422)->assertJsonValidationErrors('quote_id');
});

it('403 without work-orders.view or quotes.view; 200 with either (D-8)', function () {
    // Both permissions are pre-created BEFORE any actingAs()/can() check
    // (spatie/laravel-permission caches its permission collection on first
    // Gate resolution; creating a permission mid-test, after that cache is
    // already warm, is the documented footgun — every helper in this suite
    // pre-creates the full set upfront for the same reason).
    Permission::findOrCreate('quotes.view');
    Permission::findOrCreate('work-orders.view');

    $quote = Quote::factory()->create();

    $withNeither = User::factory()->create();
    Sanctum::actingAs($withNeither);
    $this->getJson("/api/quote-offer-lines/for-select?quote_id={$quote->id}")->assertForbidden();

    $withQuotesView = User::factory()->create();
    $withQuotesView->givePermissionTo('quotes.view');
    Sanctum::actingAs($withQuotesView);
    $this->getJson("/api/quote-offer-lines/for-select?quote_id={$quote->id}")->assertOk();

    $withWorkOrdersView = User::factory()->create();
    $withWorkOrdersView->givePermissionTo('work-orders.view');
    Sanctum::actingAs($withWorkOrdersView);
    $this->getJson("/api/quote-offer-lines/for-select?quote_id={$quote->id}")->assertOk();
});

it('401 without authentication', function () {
    $this->getJson('/api/quote-offer-lines/for-select')->assertUnauthorized();
});
