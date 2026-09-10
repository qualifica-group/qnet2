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
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Direttiva utente 2026-09-10 — on Gestione richieste ALONE the two modes of
 * POST /api/request-management/assign-operators become symmetric on the
 * server. `balanced` has always enforced Sede and competence (an offer with
 * no candidate lands in `skipped`); `single` used to accept whatever operator
 * the client sent, the filter being UI-side only (spec 0110, R-1 / spec 0113,
 * out §). It now refuses an operator no targeted offer would have accepted.
 *
 * ALL-OR-NOTHING: one offending offer rejects the whole batch with a 422 on
 * `operator_id` naming the offending ids, and nothing is written. The import
 * wizard and the Lead table are NOT touched by this directive and keep the
 * UI-only filter.
 *
 * The 403-before-422 ordering (an actor lacking `request-management.update`
 * or `.assignOperator` is answered 403, never a 422 naming offers they may
 * not act on) is covered by RequestManagementBulkActionsTest, whose two
 * permission tests target an offer that is deliberately incompatible.
 */
if (! function_exists('assignSingleActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function assignSingleActor(array $abilities): User
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

if (! function_exists('assignSingleOperator')) {
    /**
     * An operator employed at $site. Called without a function the profile
     * stays rowless: a member of the Sede competent for nothing (spec 0111,
     * D-9).
     */
    function assignSingleOperator(OperationalSite $site, ?BusinessFunction $function = null, ?ProductCategory $category = null): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($site);

        if ($function !== null && $category !== null) {
            $factory = $factory->competentIn($function, $category);
        }

        $factory->create();

        return $operator;
    }
}

if (! function_exists('assignSingleOffer')) {
    /**
     * An offer AT $site demanding exactly $category. A null $category leaves
     * the opportunity with no product line at all: an offer that demands
     * nothing, which constrains the competence half not at all (INV-4a) while
     * the Sede half still applies.
     */
    function assignSingleOffer(?OperationalSite $site, ?BusinessFunction $function = null, ?ProductCategory $category = null, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        if ($function !== null && $category !== null) {
            OpportunityProductLine::factory()->create([
                'opportunity_id' => $opportunity->id,
                'business_function_id' => $function->id,
                'product_category_id' => $category->id,
            ]);
        }

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $site?->id,
            'operator_id' => $operator?->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// The operator every targeted offer would have accepted goes through.
// ---------------------------------------------------------------------------

it('single: an operator valid for every targeted offer is assigned to all of them', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $chosen = assignSingleOperator($site, $function, $category);

    $firstOffer = assignSingleOffer($site, $function, $category);
    $secondOffer = assignSingleOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstOffer->id, $secondOffer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($firstOffer->fresh()->operator_id)->toBe($chosen->id)
        ->and($secondOffer->fresh()->operator_id)->toBe($chosen->id);
});

// ---------------------------------------------------------------------------
// The two halves of "valid", each refused on its own.
// ---------------------------------------------------------------------------

it('single: an operator competent but employed at another Sede is refused with a 422 naming the offer', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $offerSite = OperationalSite::factory()->withAddress()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    // Competent for exactly what the offer demands, but at the wrong Sede.
    $chosen = assignSingleOperator($otherSite, $function, $category);
    $incumbent = User::factory()->create();
    $offer = assignSingleOffer($offerSite, $function, $category, $incumbent);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    // The offending offer is named by the id the client itself submitted, and
    // the envelope leaks no internal class or model name.
    expect($response->json('errors.operator_id.0'))->toContain((string) $offer->id)
        ->not->toContain('Quote')
        ->and($offer->fresh()->operator_id)->toBe($incumbent->id);
});

it('single: an operator of the right Sede but not competent for the offer is refused with a 422', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $otherFunction = BusinessFunction::factory()->create();
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $otherFunction->id]);

    // Same Sede as the offer, competent for something else entirely.
    $chosen = assignSingleOperator($site, $otherFunction, $otherCategory);
    $offer = assignSingleOffer($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    expect($offer->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// All-or-nothing: one offending offer holds the whole batch back.
// ---------------------------------------------------------------------------

it('single: one incompatible offer out of three rejects the batch and writes nothing at all', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $orphanFunction = BusinessFunction::factory()->create();
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $chosen = assignSingleOperator($site, $function, $category);
    $incumbent = User::factory()->create();

    $firstOffer = assignSingleOffer($site, $function, $category, $incumbent);
    $secondOffer = assignSingleOffer($site, $function, $category, $incumbent);
    $offendingOffer = assignSingleOffer($site, $orphanFunction, $orphanCategory, $incumbent);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstOffer->id, $secondOffer->id, $offendingOffer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    // Only the offending one is named, and the two that WOULD have been
    // assignable are left exactly as they were.
    expect($response->json('errors.operator_id.0'))->toContain((string) $offendingOffer->id)
        ->and($firstOffer->fresh()->operator_id)->toBe($incumbent->id)
        ->and($secondOffer->fresh()->operator_id)->toBe($incumbent->id)
        ->and($offendingOffer->fresh()->operator_id)->toBe($incumbent->id);
});

// ---------------------------------------------------------------------------
// INV-4a — no requirement does not mean no Sede.
// ---------------------------------------------------------------------------

it('single: an offer demanding no product category accepts any operator of its Sede', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    // A member of the Sede competent for nothing at all.
    $chosen = assignSingleOperator($site);
    $offer = assignSingleOffer($site);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$offer->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($offer->fresh()->operator_id)->toBe($chosen->id);
});

it('single: an offer with no Sede at all has no valid operator and rejects the batch', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $chosen = assignSingleOperator($site);
    // No Sede means an EMPTY pool (AC-007), never a fallback onto everybody:
    // there is no operator `balanced` could have distributed this offer to,
    // so `single` may not write one either.
    $siteless = assignSingleOffer(null);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$siteless->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    expect($siteless->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// D-3 — an offer the actor cannot reach does not exist for the check either.
// ---------------------------------------------------------------------------

it('single: an incompatible offer outside the actor D-3 scope does not cause a 422', function () {
    $actor = assignSingleActor(['viewAny', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $chosen = assignSingleOperator($site, $function, $category);

    // In scope: the actor operates it, and $chosen covers it. Out of scope:
    // another Sede entirely, so $chosen covers it NOT — were it visible to
    // the check it would fail the batch, and that failure would itself
    // disclose an offer this actor may not see.
    $reachable = assignSingleOffer($site, $function, $category, $actor);
    $unreachable = assignSingleOffer($otherSite, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$reachable->id, $unreachable->id],
        'mode' => 'single',
        'operator_id' => $chosen->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($reachable->fresh()->operator_id)->toBe($chosen->id)
        ->and($unreachable->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// `balanced` is untouched: an uncovered offer is still a skip, never a 422.
// ---------------------------------------------------------------------------

it('balanced: an offer no operator covers is still skipped with a 200, not rejected', function () {
    $actor = assignSingleActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $orphanFunction = BusinessFunction::factory()->create();
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $operator = assignSingleOperator($site, $function, $category);
    $covered = assignSingleOffer($site, $function, $category);
    $uncovered = assignSingleOffer($site, $orphanFunction, $orphanCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$covered->id, $uncovered->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    expect($covered->fresh()->operator_id)->toBe($operator->id)
        ->and($uncovered->fresh()->operator_id)->toBeNull();
});
