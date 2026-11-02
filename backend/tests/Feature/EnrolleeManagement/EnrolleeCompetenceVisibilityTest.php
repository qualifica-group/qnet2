<?php

use App\Models\Quote;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0340 (MT-5): `enrollee-management.viewCompetence`; the module's status
 * filter (validated / closed_won) stays applied ahead of the competence level.
 */
uses(RefreshDatabase::class);

function competenceEnrolleeRowIds($test): array
{
    return collect($test->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('shows the enrollees in my competence and not the out-of-state ones (AC-010)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('enrollee-management');
    grantCompetence($actor, $function, $category);
    $validated = categoryTabRequestQuote([$category], 'validated');
    $closedWon = categoryTabRequestQuote([$category], 'closed_won');
    $open = categoryTabRequestQuote([$category], 'open');
    Sanctum::actingAs($actor);

    expect(competenceEnrolleeRowIds($this))->toBe(collect([$validated->id, $closedWon->id])->sort()->values()->all())
        ->not->toContain($open->id);
    $this->getJson("/api/enrollee-management/{$validated->id}")->assertOk();
    $this->getJson("/api/enrollee-management/{$open->id}")->assertForbidden();
});

it('with the all-categories flag still honours the status filter (AC-010)', function () {
    $actor = competenceVisibilityActor('enrollee-management');
    grantCompetenceOnAllCategories($actor);
    $validated = categoryTabRequestQuote([], 'validated');
    $open = categoryTabRequestQuote([], 'open');
    Sanctum::actingAs($actor);

    expect(competenceEnrolleeRowIds($this))->toContain($validated->id)->not->toContain($open->id);
});

it('without the ability or without competence nothing is added (AC-012)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $withoutAbility = competenceVisibilityActor('enrollee-management', ['viewAny', 'view']);
    grantCompetence($withoutAbility, $function, $category);
    $withoutCompetence = competenceVisibilityActor('enrollee-management');
    $operated = categoryTabRequestQuote([$category], 'validated');
    DB::table('quotes')->where('id', $operated->id)->update(['operator_id' => $withoutCompetence->id]);

    Sanctum::actingAs($withoutAbility);
    expect(competenceEnrolleeRowIds($this))->toBe([]);

    app()->forgetScopedInstances();
    Sanctum::actingAs($withoutCompetence);
    expect(competenceEnrolleeRowIds($this))->toBe([$operated->id]);
});

it('stays fail-closed for a null actor (AC-020)', function () {
    categoryTabRequestQuote([], 'validated');

    expect(RequestManagementScope::scopeToActor(Quote::query(), null, RequestModule::Enrollees)->count())->toBe(0);
});
