<?php

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The `quotes.viewCompetence` record-visibility tier (spec 0340): the quotes
 * whose REVENUE lines fall in the actor's assignment competence, UNIONed with
 * the own / site tiers. A row gate, never an action gate (D-5).
 */
uses(RefreshDatabase::class);

function competenceQuoteIds(): array
{
    return collect(test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 100])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();
}

function competenceQuoteQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 100])->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('AC-001: shows the quotes with revenue in a covered category and hides the uncovered ones', function () {
    [$function, $covered] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes');
    grantCompetence($actor, $function, $covered);
    $inScope = categoryTabQuote([$covered]);
    categoryTabQuote([$other]);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([$inScope->id]);
    $this->getJson("/api/quotes/{$inScope->id}")->assertOk();
});

it('AC-002: without the ability the same quote is absent and the show is 403 with the out-of-scope message', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes', ['viewAny', 'view']);
    grantCompetence($actor, $function, $covered);
    $quote = categoryTabQuote([$covered]);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([]);
    $this->getJson("/api/quotes/{$quote->id}", ['Accept-Language' => 'it'])->assertForbidden()
        ->assertJsonPath('message', trans('authorization.quote_out_of_scope', [], 'it'));
});

it('AC-003: a parent row reaches the child categories, a (function, all) row reaches only that function', function () {
    [$function, $parent] = competenceVisibilityCategory();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id, 'business_function_id' => null]);
    [, $foreign] = competenceVisibilityCategory();
    $viaParent = categoryTabQuote([$child]);
    $foreignQuote = categoryTabQuote([$foreign]);

    $parentActor = competenceVisibilityActor('quotes');
    grantCompetence($parentActor, $function, $parent);
    Sanctum::actingAs($parentActor);
    expect(competenceQuoteIds())->toBe([$viaParent->id]);

    $wildcardActor = competenceVisibilityActor('quotes');
    grantCompetenceOnEveryCategoryOf($wildcardActor, $function);
    Sanctum::actingAs($wildcardActor);
    app()->forgetScopedInstances();
    expect(competenceQuoteIds())->toContain($viaParent->id)->not->toContain($foreignQuote->id);
});

it('AC-004: a category present only on a COST line does not make the quote of competence', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes');
    grantCompetence($actor, $function, $covered);
    categoryTabQuote([$covered], cost: true);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([]);
});

it('AC-005: a quote with no revenue lines is visible only with the all-categories flag', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $bare = Quote::factory()->create();

    $scopedActor = competenceVisibilityActor('quotes');
    grantCompetence($scopedActor, $function, $covered);
    Sanctum::actingAs($scopedActor);
    expect(competenceQuoteIds())->toBe([]);

    $wildcardActor = competenceVisibilityActor('quotes');
    grantCompetenceOnAllCategories($wildcardActor);
    Sanctum::actingAs($wildcardActor);
    app()->forgetScopedInstances();
    expect(competenceQuoteIds())->toBe([$bare->id]);
    $this->getJson("/api/quotes/{$bare->id}")->assertOk();
});

it('AC-011: the Assignable switch does not influence the tier', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes');
    grantCompetence($actor, $function, $covered)->forceFill(['is_assignable' => false])->save();
    $quote = categoryTabQuote([$covered]);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([$quote->id]);
});

it('AC-012: viewCompetence with no competence at all adds nothing', function () {
    $actor = competenceVisibilityActor('quotes');
    $mine = Quote::factory()->create(['supervisor_id' => $actor->id]);
    categoryTabQuote([competenceVisibilityCategory()[1]]);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([$mine->id]);
});

it('AC-013: the tier is a row gate, update and delete need their own ability', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $quote = categoryTabQuote([$covered]);

    $viewer = competenceVisibilityActor('quotes');
    grantCompetence($viewer, $function, $covered);
    Sanctum::actingAs($viewer);
    $this->deleteJson("/api/quotes/{$quote->id}")->assertForbidden();
    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'x'])->assertForbidden();

    $editor = competenceVisibilityActor('quotes', ['viewAny', 'view', 'viewCompetence', 'update', 'delete']);
    grantCompetence($editor, $function, $covered);
    Sanctum::actingAs($editor);
    app()->forgetScopedInstances();
    expect($this->patchJson("/api/quotes/{$quote->id}", ['title' => 'x'])->status())->not->toBe(403);
    expect($this->deleteJson("/api/quotes/{$quote->id}")->status())->not->toBe(403);
});

it('AC-014: own, site-less and competence tiers are a union that still obeys the grid filter', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes');
    grantCompetence($actor, $function, $covered);
    $own = Quote::factory()->create(['supervisor_id' => $actor->id, 'title' => 'keep-own']);
    $byCompetence = categoryTabQuote([$covered]);
    $byCompetence->update(['title' => 'keep-comp']);
    $filteredOut = categoryTabQuote([$covered]);
    $filteredOut->update(['title' => 'drop']);
    Quote::factory()->create(['title' => 'keep-foreign']);
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/quotes/rows', [
        'startRow' => 0, 'endRow' => 100,
        'filterModel' => ['title' => ['filterType' => 'text', 'type' => 'startsWith', 'filter' => 'keep']],
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$own->id, $byCompetence->id])->sort()->values()->all());
});

it('AC-016: the row query count does not grow with the competence-only rows', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('quotes');
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    competenceQuoteQueryCount(); // warm-up: permission and competence caches are filled once

    foreach (range(1, 5) as $unused) {
        categoryTabQuote([$covered], opportunity: Opportunity::factory()->create());
    }
    $withFive = competenceQuoteQueryCount();

    foreach (range(1, 15) as $unused) {
        categoryTabQuote([$covered], opportunity: Opportunity::factory()->create());
    }
    $withTwenty = competenceQuoteQueryCount();

    expect(competenceQuoteIds())->toHaveCount(20)->and($withTwenty)->toBe($withFive);
});

it('AC-020: a null actor matches no quote', function () {
    categoryTabQuote([competenceVisibilityCategory()[1]]);

    expect(QuoteVisibilityScope::scopeToActor(Quote::query(), null)->count())->toBe(0);
});

it('AC-016: projects the competence verdict only for an actor it can decide', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $bounded = competenceVisibilityActor('quotes');
    grantCompetence($bounded, $function, $covered);
    $seesAll = competenceVisibilityActor('quotes', ['viewAny', 'view', 'viewCompetence', 'viewAll']);
    grantCompetence($seesAll, $function, $covered);

    expect(QuoteVisibilityScope::withCompetenceVerdict(Quote::query(), $bounded)->toSql())
        ->toContain(QuoteVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE)
        ->and(QuoteVisibilityScope::withCompetenceVerdict(Quote::query(), $seesAll)->toSql())
        ->not->toContain(QuoteVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE);
});

it('AC-016: an actor who sees every quote still gets rows outside the competence', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $inside = categoryTabQuote([$covered]);
    $outside = categoryTabQuote([competenceVisibilityCategory()[1]]);
    $actor = competenceVisibilityActor('quotes', ['viewAny', 'view', 'viewCompetence', 'viewAll']);
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    expect(competenceQuoteIds())->toBe([$inside->id, $outside->id]);
});
