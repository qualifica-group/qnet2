<?php

use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * quotes.quote_date (spec 0228): always set, today when a channel does not
 * send it, editable on create and update, never null.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-12 09:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

if (! function_exists('quoteDatePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function quoteDatePayload(array $overrides = []): array
    {
        return array_merge([
            'opportunity_id' => Opportunity::factory()->create()->id,
            'offer_lines' => [['product_id' => quoteHttpRevenueProduct()->id, 'quantity' => 1, 'unit_price' => 10]],
        ], $overrides);
    }
}

it('defaults the offer date to today when a channel does not send it (AC-001)', function () {
    $quote = nameDerivationQuoteService()->create(
        nameDerivationCreateQuoteData(Opportunity::factory()->create()->id),
        nameDerivationActor(),
    );

    expect($quote->quote_date->toDateString())->toBe('2026-10-12');
});

it('stores the submitted offer date on create and exposes it (AC-002)', function () {
    Sanctum::actingAs(quoteTableUserWith(['create', 'view']));

    $response = $this->postJson('/api/quotes', quoteDatePayload(['quote_date' => '2026-09-30']));

    $response->assertCreated()->assertJsonPath('data.quote_date', '2026-09-30');
    expect(Quote::query()->sole()->quote_date->toDateString())->toBe('2026-09-30');
});

it('creates with today when the offer date is omitted from the request', function () {
    Sanctum::actingAs(quoteTableUserWith(['create', 'view']));

    $this->postJson('/api/quotes', quoteDatePayload())
        ->assertCreated()
        ->assertJsonPath('data.quote_date', '2026-10-12');
});

it('rejects a null or malformed offer date on create', function (mixed $value) {
    Sanctum::actingAs(quoteTableUserWith(['create', 'view']));

    $this->postJson('/api/quotes', quoteDatePayload(['quote_date' => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quote_date');
})->with([null, '30/09/2026', 'not-a-date']);

it('updates the offer date and refuses to clear it', function () {
    Sanctum::actingAs(quoteTableUserWith(['update', 'view']));
    $quote = Quote::factory()->create(['quote_date' => '2026-01-01']);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_date' => '2026-02-15'])
        ->assertOk()
        ->assertJsonPath('data.quote_date', '2026-02-15');

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_date' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quote_date');

    expect($quote->fresh()->quote_date->toDateString())->toBe('2026-02-15');
});

it('refuses an offer date change from an actor without update rights', function () {
    Sanctum::actingAs(quoteTableUserWith(['view']));
    $quote = Quote::factory()->create(['quote_date' => '2026-01-01']);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_date' => '2026-02-15'])->assertForbidden();

    expect($quote->fresh()->quote_date->toDateString())->toBe('2026-01-01');
});
