<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0113, AC-019/AC-021 (Gestione richieste half) — POST
 * /api/request-management/assign-operators no longer takes a Sede: each offer
 * is distributed among the operators of its OWN `quotes.operational_site_id`
 * (D-4) competent for its own product lines, and that column is read, never
 * written.
 *
 * AC-020 (an out-of-scope offer stays silently untouched) is unchanged by
 * this spec and stays covered by RequestManagementAssignCompetenceTest, which
 * owns the D-3 scope cases of this endpoint.
 */
if (! function_exists('requestSiteScopeActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestSiteScopeActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("request-management.{$ability}");
        }

        return $actor;
    }
}

if (! function_exists('requestSiteScopeOperator')) {
    /** An operator employed at $site and competent for $category (spec 0111 D-2). */
    function requestSiteScopeOperator(OperationalSite $site, BusinessFunction $function, ProductCategory $category): User
    {
        $operator = User::factory()->create();

        EmploymentProfile::factory()
            ->for($operator)
            ->physicalSite($site)
            ->competentIn($function, $category)
            ->create();

        return $operator;
    }
}

if (! function_exists('requestSiteScopeOffer')) {
    /**
     * An offer AT $site (null included: an offer with no Sede has an empty
     * pool by construction, AC-007) demanding exactly $category.
     */
    function requestSiteScopeOffer(?OperationalSite $site, BusinessFunction $function, ProductCategory $category, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $site?->id,
            'operator_id' => $operator?->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-019 — every offer is distributed inside its OWN Sede.
// ---------------------------------------------------------------------------

it('0113 AC-019: mode=balanced sends each offer to an operator of its own Sede, never to the other Sede', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $firstSite = OperationalSite::factory()->withAddress()->create();
    $secondSite = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    // Same competence on both sides: only the Sede tells the two pools apart.
    $firstSiteOperator = requestSiteScopeOperator($firstSite, $function, $category);
    $secondSiteOperator = requestSiteScopeOperator($secondSite, $function, $category);

    $firstSiteOffer = requestSiteScopeOffer($firstSite, $function, $category);
    $secondSiteOffer = requestSiteScopeOffer($secondSite, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstSiteOffer->id, $secondSiteOffer->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstSiteOffer->fresh()->operator_id)->toBe($firstSiteOperator->id)
        ->and($secondSiteOffer->fresh()->operator_id)->toBe($secondSiteOperator->id);
});

it('0113 AC-019: an offer competent-but-elsewhere finds no candidate and is skipped', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $offerSite = OperationalSite::factory()->withAddress()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    // The only competent operator in the system sits at the WRONG Sede.
    $foreignOperator = requestSiteScopeOperator($otherSite, $function, $category);
    $incumbent = User::factory()->create();
    $offer = requestSiteScopeOffer($offerSite, $function, $category, $incumbent);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 1);

    expect($offer->fresh()->operator_id)->toBe($incumbent->id)
        ->not->toBe($foreignOperator->id);
});

it('0113 AC-019: the load that steers the distribution counts the OFFERS each operator already operates', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $busyOperator = requestSiteScopeOperator($site, $function, $category);
    $freeOperator = requestSiteScopeOperator($site, $function, $category);

    // Not part of the batch: it only weighs $busyOperator down, and it is an
    // OFFER, the signal this module distributes on (AC-033).
    requestSiteScopeOffer($site, $function, $category, $busyOperator);

    $firstOffer = requestSiteScopeOffer($site, $function, $category);
    $secondOffer = requestSiteScopeOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstOffer->id, $secondOffer->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    // The idle one takes the first offer; that levels the two loads, and the
    // tie goes to the lowest operator id (br-balanced step 3).
    expect($firstOffer->fresh()->operator_id)->toBe($freeOperator->id)
        ->and($secondOffer->fresh()->operator_id)->toBe($busyOperator->id);
});

// ---------------------------------------------------------------------------
// AC-019 — the Sede is read, never written, and never audited.
// ---------------------------------------------------------------------------

it('0113 AC-019: the assignment rewrites no Sede and logs no Sede change', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestSiteScopeOperator($site, $function, $category);

    $offer = requestSiteScopeOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    $activities = Activity::query()
        ->where('subject_id', $offer->opportunity_id)
        ->where('description', 'Request management bulk assignment')
        ->get();

    expect($offer->fresh()->operational_site_id)->toBe($site->id)
        ->and($offer->fresh()->operator_id)->toBe($operator->id)
        ->and($activities)->toHaveCount(1)
        ->and($activities->first()->properties->get('attributes'))->toHaveKey('operator_id')
        ->and($activities->first()->properties->get('attributes'))->not->toHaveKey('operational_site_id')
        // Nowhere else either: nothing this actor caused mentions the Sede.
        // (Scoped by causer: the fixtures' own creation logs legitimately
        // carry the column, and they are caused by nobody.)
        ->and(Activity::query()
            ->where('causer_id', $actor->id)
            ->where('properties', 'like', '%operational_site_id%')
            ->count())->toBe(0);
});

it('0113 AC-019: an offer whose only change would have been the Sede is left alone entirely', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestSiteScopeOperator($site, $function, $category);

    // Already on the operator the distribution would pick: nothing moves, so
    // the "nothing changed" guard now rests on the operator alone.
    $offer = requestSiteScopeOffer($site, $function, $category, $operator);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect(Activity::query()->where('description', 'Request management bulk assignment')->count())->toBe(0)
        ->and($offer->fresh()->operator_id)->toBe($operator->id);
});

// ---------------------------------------------------------------------------
// An offer with no Sede at all: empty pool, kept operator, still a 200.
// ---------------------------------------------------------------------------

it('0113: an offer with no Sede keeps its operator, is skipped, and does not fail the batch', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestSiteScopeOperator($site, $function, $category);
    $incumbent = User::factory()->create();

    $siteless = requestSiteScopeOffer(null, $function, $category, $incumbent);
    $sited = requestSiteScopeOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$siteless->id, $sited->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    // No Sede means an EMPTY pool, never a fallback onto every operator.
    expect($siteless->fresh()->operator_id)->toBe($incumbent->id)
        ->and($siteless->fresh()->operational_site_id)->toBeNull()
        ->and($sited->fresh()->operator_id)->toBe($operator->id);
});

// ---------------------------------------------------------------------------
// AC-021 — the Sede left the contract.
// ---------------------------------------------------------------------------

it('0113 AC-021: a payload still carrying operational_site_id is rejected with 422', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestSiteScopeOperator($site, $function, $category);

    $offer = requestSiteScopeOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['operational_site_id']);

    expect($offer->fresh()->operator_id)->toBeNull();
});

it('0113 AC-021: mode=single is unchanged otherwise — no competence check, no Sede write', function () {
    $actor = requestSiteScopeActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    // Neither competent for the offer nor employed at its Sede: `single`
    // still assigns them (spec 0110, R-1 — the filter is UI-side only).
    $otherFunction = BusinessFunction::factory()->create();
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $otherFunction->id]);
    $chosen = requestSiteScopeOperator($otherSite, $otherFunction, $otherCategory);

    $offer = requestSiteScopeOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($offer->fresh()->operator_id)->toBe($chosen->id)
        ->and($offer->fresh()->operational_site_id)->toBe($site->id);
});
