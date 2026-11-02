<?php

use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Services\Opportunities\OpportunityVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0340 (MT-4): `opportunities.viewCompetence` opens, in UNION with the
 * supervisor/manager level, the opportunities with a product line in the
 * actor's assignment competence.
 */
uses(RefreshDatabase::class);

function competenceOpportunityRowIds($test, array $body = []): array
{
    return collect($test->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 50, ...$body])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('rows, show and for-select include the opportunities in my competence (AC-008)', function () {
    [$function, $category] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $category);
    $mine = categoryTabOpportunity($category);
    $foreign = categoryTabOpportunity($other);
    Sanctum::actingAs($actor);

    expect(competenceOpportunityRowIds($this))->toBe([$mine->id]);
    $this->getJson("/api/opportunities/{$mine->id}")->assertOk();
    $this->getJson("/api/opportunities/{$foreign->id}")->assertForbidden();

    $ids = collect($this->getJson("/api/opportunities/for-select?ids[]={$mine->id}&ids[]={$foreign->id}")->assertOk()->json('items'))->pluck('id');
    expect($ids->all())->toContain($mine->id)->not->toContain($foreign->id);
});

it('without the ability the same opportunity is not listed and show answers 403 (AC-008)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities', ['viewAny', 'view']);
    grantCompetence($actor, $function, $category);
    $opportunity = categoryTabOpportunity($category);
    Sanctum::actingAs($actor);

    expect(competenceOpportunityRowIds($this))->toBe([]);
    $this->getJson("/api/opportunities/{$opportunity->id}", ['Accept-Language' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('message', trans('authorization.opportunity_out_of_scope', [], 'it'));
});

it('a mother category row reaches the opportunities of its daughter (AC-003)', function () {
    [$function, $mother] = competenceVisibilityCategory();
    $daughter = ProductCategory::factory()->create(['parent_id' => $mother->id, 'business_function_id' => $function->id]);
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $mother);
    $opportunity = categoryTabOpportunity($daughter);
    Sanctum::actingAs($actor);

    expect(competenceOpportunityRowIds($this))->toBe([$opportunity->id]);
});

it('the all-categories flag sees every opportunity, even one without lines (D-3)', function () {
    $actor = competenceVisibilityActor('opportunities');
    grantCompetenceOnAllCategories($actor);
    $bare = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    expect(competenceOpportunityRowIds($this))->toContain($bare->id);
    $this->getJson("/api/opportunities/{$bare->id}")->assertOk();
});

it('an actor with the ability but no competence sees what they saw before (AC-012)', function () {
    [, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    $supervised = Opportunity::factory()->create(['supervisor_id' => $actor->id]);
    categoryTabOpportunity($category);
    Sanctum::actingAs($actor);

    expect(competenceOpportunityRowIds($this))->toBe([$supervised->id]);
});

it('keeps the union of own and competence levels inside a grid filter (AC-014)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $category);
    $own = Opportunity::factory()->create(['supervisor_id' => $actor->id, 'name' => 'Needle own']);
    $competent = categoryTabOpportunity($category);
    $competent->update(['name' => 'Needle competent']);
    $hidden = Opportunity::factory()->create(['name' => 'Needle hidden']);
    $filtered = categoryTabOpportunity($category);
    $filtered->update(['name' => 'Haystack']);
    Sanctum::actingAs($actor);

    $ids = competenceOpportunityRowIds($this, ['filterModel' => ['name' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Needle']]]);

    expect($ids)->toBe(collect([$own->id, $competent->id])->sort()->values()->all())
        ->not->toContain($hidden->id, $filtered->id);
});

it('does not turn the competence level into an update grant (AC-013)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $category);
    $opportunity = categoryTabOpportunity($category);
    Sanctum::actingAs($actor);

    $this->putJson("/api/opportunities/{$opportunity->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->deleteJson("/api/opportunities/{$opportunity->id}")->assertForbidden();
});

it('stays fail-closed for a null actor (AC-020)', function () {
    categoryTabOpportunity(competenceVisibilityCategory()[1]);

    $ids = OpportunityVisibilityScope::scopeToActor(Opportunity::query(), null)->pluck('id');

    expect($ids)->toBeEmpty();
});

it('isVisibleTo and the query shape agree for loaded and unloaded relations (AC-008)', function () {
    [$function, $category] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $category);
    $inScope = categoryTabOpportunity($category);
    $outOfScope = categoryTabOpportunity($other);
    $verdict = fn (Opportunity $o) => OpportunityVisibilityScope::isVisibleTo($actor, $o);

    expect($verdict($inScope))->toBeTrue()
        ->and($verdict($outOfScope))->toBeFalse()
        ->and($verdict($inScope->load(['managers', 'productLines'])))->toBeTrue()
        ->and($verdict($outOfScope->load(['managers', 'productLines'])))->toBeFalse();
});

it('does not grow the query count with the rows visible only by competence (AC-016)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('opportunities');
    grantCompetence($actor, $function, $category);
    Sanctum::actingAs($actor);
    competenceOpportunityRowIds($this); // warm-up: the permission cache load is not per row

    $countQueries = function () {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        competenceOpportunityRowIds($this);

        return count(DB::getQueryLog());
    };

    foreach (range(1, 5) as $ignored) {
        categoryTabOpportunity($category);
    }
    $withFive = $countQueries();

    foreach (range(1, 15) as $ignored) {
        categoryTabOpportunity($category);
    }
    $withTwenty = $countQueries();

    expect($withTwenty)->toBe($withFive);
});
