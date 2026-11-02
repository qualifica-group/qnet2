<?php

use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Spec 0340 (MT-5): `request-management.viewCompetence` opens, in UNION with the
 * operator and Sede levels, the requests whose opportunity has a product line
 * in the actor's assignment competence, on every Sede.
 */
uses(RefreshDatabase::class);

function competenceRequestRowIds($test, array $body = []): array
{
    return collect($test->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 50, ...$body])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

function competenceRequestActor(array $extra = []): User
{
    return competenceVisibilityActor('request-management', ['viewAny', 'view', 'viewCompetence', ...$extra]);
}

it('grid, detail and category tab counts include the requests in my competence (AC-009)', function () {
    [$function, $category] = competenceVisibilityCategory();
    [, $other] = competenceVisibilityCategory();
    $actor = competenceRequestActor();
    grantCompetence($actor, $function, $category);
    $mine = categoryTabRequestQuote([$category]);
    $foreign = categoryTabRequestQuote([$other]);
    Sanctum::actingAs($actor);

    expect(competenceRequestRowIds($this))->toBe([$mine->id]);
    $this->getJson("/api/request-management/{$mine->id}")->assertOk();
    $this->getJson("/api/request-management/{$foreign->id}")->assertForbidden();

    $tabs = $this->getJson('/api/request-management/product-categories')->assertOk()->json('data.categories');
    expect(collect($tabs)->pluck('requests_count', 'id')->all())->toBe([$category->id => 1]);
});

it('without the ability assertInScope answers 403 with the updated message (AC-009)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceVisibilityActor('request-management', ['viewAny', 'view']);
    grantCompetence($actor, $function, $category);
    $quote = categoryTabRequestQuote([$category]);

    try {
        (new RequestManagementScope)->assertInScope($actor, $quote);
        $this->fail('assertInScope must abort');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)
            ->and($e->getMessage())->toContain('outside your assignment scope');
    }

    expect(RequestManagementScope::isInActorCompetence($actor, $quote))->toBeFalse();
});

it('an actor with the ability but no competence sees what they saw before (AC-012)', function () {
    [, $category] = competenceVisibilityCategory();
    $actor = competenceRequestActor();
    $operated = Quote::factory()->create(['operator_id' => $actor->id]);
    categoryTabRequestQuote([$category]);
    Sanctum::actingAs($actor);

    expect(competenceRequestRowIds($this))->toBe([$operated->id]);
});

it('keeps the union of operator, Sede and competence inside a grid filter (AC-014)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceRequestActor();
    grantCompetence($actor, $function, $category);
    $fair = Source::factory()->create(['name' => 'Fiera']);
    $web = Source::factory()->create(['name' => 'Web']);
    $operated = Quote::factory()->create(['operator_id' => $actor->id, 'source_id' => $fair->id]);
    $competent = categoryTabRequestQuote([$category]);
    $competent->update(['source_id' => $fair->id]);
    $competentOtherSource = categoryTabRequestQuote([$category]);
    $competentOtherSource->update(['source_id' => $web->id]);
    $hidden = Quote::factory()->create(['source_id' => $fair->id]);
    Sanctum::actingAs($actor);

    $ids = competenceRequestRowIds($this, ['filterModel' => ['source' => ['filterType' => 'set', 'values' => ['Fiera']]]]);

    expect($ids)->toBe(collect([$operated->id, $competent->id])->sort()->values()->all())
        ->not->toContain($competentOtherSource->id, $hidden->id);
});

it('does not turn the competence level into an action grant (AC-013)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceRequestActor(['update']);
    grantCompetence($actor, $function, $category);
    $quote = categoryTabRequestQuote([$category]);
    $operator = competenceVisibilityActor('request-management', ['view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertForbidden();
    $this->deleteJson("/api/request-management/{$quote->id}")->assertForbidden();

    $actor->givePermissionTo(Permission::findOrCreate('request-management.delete', 'web'));
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->deleteJson("/api/request-management/{$quote->id}")->assertSuccessful();
});

it('fingerprint differs when the competence differs and is unchanged without the ability (AC-015)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $withAbility = competenceRequestActor();
    $without = competenceVisibilityActor('request-management', ['viewAny', 'view']);
    $before = RequestManagementScope::scopeFingerprint($withAbility, RequestModule::Requests);
    $plain = RequestManagementScope::scopeFingerprint($without, RequestModule::Requests);

    grantCompetence($withAbility, $function, $category);
    grantCompetence($without, $function, $category);
    app()->forgetScopedInstances();
    $after = RequestManagementScope::scopeFingerprint($withAbility, RequestModule::Requests);

    expect($after)->not->toBe($before)
        ->and($before)->toEndWith(':comp:')
        ->and(RequestManagementScope::scopeFingerprint($without, RequestModule::Requests))->toBe($plain)
        ->and($plain)->toBe("request-management:op:{$without->id}:sites:");
});

it('stays fail-closed for a null actor (AC-020)', function () {
    categoryTabRequestQuote([competenceVisibilityCategory()[1]]);

    expect(RequestManagementScope::scopeToActor(Quote::query(), null)->pluck('quotes.id'))->toBeEmpty();
});

it('does not grow the query count with the rows visible only by competence (AC-016)', function () {
    [$function, $category] = competenceVisibilityCategory();
    $actor = competenceRequestActor();
    grantCompetence($actor, $function, $category);
    Sanctum::actingAs($actor);
    competenceRequestRowIds($this); // warm-up: the permission cache load is not per row

    $countQueries = function () {
        app()->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        competenceRequestRowIds($this);

        return count(DB::getQueryLog());
    };

    foreach (range(1, 5) as $ignored) {
        categoryTabRequestQuote([$category]);
    }
    $withFive = $countQueries();

    foreach (range(1, 15) as $ignored) {
        categoryTabRequestQuote([$category]);
    }

    expect($countQueries())->toBe($withFive);
});
