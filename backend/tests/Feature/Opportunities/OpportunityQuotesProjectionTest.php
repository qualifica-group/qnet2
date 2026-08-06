<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0085: `quotes` on OpportunityResource — a compact `{id, code, title}`
 * per Offerta, ordered by `code`, feeding the notes list's `quote_scope`
 * filter and the composer's destination selector. Additive, alongside the
 * pre-existing `quotes_count` (OpportunityQuotesCountTest).
 */
uses(RefreshDatabase::class);

if (! function_exists('opportunityQuotesCountViewer')) {
    function opportunityQuotesCountViewer(): User
    {
        Permission::findOrCreate('opportunities.view');
        Permission::findOrCreate('opportunities.delete');
        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.view', 'opportunities.delete']);

        return $user;
    }
}

if (! function_exists('quoteWithCode')) {
    function quoteWithCode(Opportunity $opportunity, string $code, string $title): Quote
    {
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'title' => $title]);
        $quote->forceFill(['code' => $code])->save();

        return $quote;
    }
}

it('GET opportunity with no quotes exposes quotes as an empty array', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.quotes', []);
});

it('GET opportunity with quotes exposes the compact {id, code, title} projection, ordered by code', function () {
    $actor = opportunityQuotesCountViewer();
    $opportunity = Opportunity::factory()->create();
    $second = quoteWithCode($opportunity, 'QUO-0002', 'Seconda offerta');
    $first = quoteWithCode($opportunity, 'QUO-0001', 'Prima offerta');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/opportunities/{$opportunity->id}")->assertOk();

    expect($response->json('data.quotes'))->toBe([
        ['id' => $first->id, 'code' => 'QUO-0001', 'title' => 'Prima offerta'],
        ['id' => $second->id, 'code' => 'QUO-0002', 'title' => 'Seconda offerta'],
    ]);
});

it('the quotes projection does not add an N+1 query as the number of quotes grows', function () {
    $actor = opportunityQuotesCountViewer();

    $small = Opportunity::factory()->create();
    Quote::factory()->for($small)->count(1)->create();

    $large = Opportunity::factory()->create();
    Quote::factory()->for($large)->count(10)->create();

    Sanctum::actingAs($actor);

    // Warm Spatie's permission cache and CustomFieldProvider's per-request
    // memo before measuring, mirroring OpportunityQuotesCountTest's own
    // precedent, so neither call absorbs a size-unrelated, one-off query.
    $actor->can('opportunities.view');
    $this->getJson("/api/opportunities/{$small->id}");

    DB::enableQueryLog();
    $this->getJson("/api/opportunities/{$small->id}")->assertOk()->assertJsonCount(1, 'data.quotes');
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->getJson("/api/opportunities/{$large->id}")->assertOk()->assertJsonCount(10, 'data.quotes');
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});
