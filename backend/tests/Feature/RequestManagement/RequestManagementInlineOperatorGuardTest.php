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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Direttiva utente 2026-09-10 — the inline `operator_ga2` cell writes the SAME
 * GA2 slot the bulk endpoint does, so it may no longer take an operator the
 * offer itself would have refused. The guard lives in
 * RequestAttributionWriter::applyOperator(), the one caller of which is
 * RequestManagementService::updateWork() on its `operator_id` key — reached by
 * this cell alone (the work panel's PATCH `prohibits` that key and writes
 * `manager_slots` instead).
 *
 * ONE CLAUSE DIFFERS FROM THE BULK, DELIBERATELY: an offer with NO Sede is
 * judged on COMPETENCE ALONE here and stays editable, whereas the bulk
 * endpoint treats it as incompatible (RequestManagementAssignSingleOperatorTest).
 * The reason is that the bulk picks ONE operator for EVERY targeted offer, so
 * an offer able to express no candidate makes that single choice arbitrary;
 * here the operator is chosen for THIS offer alone, and applying the bulk rule
 * would leave a Sede-less offer unassignable until somebody gave it a Sede —
 * a dead end whose cause is invisible to whoever hits it.
 *
 * These tests exist to keep somebody from "aligning" the two for consistency.
 */
if (! function_exists('inlineGuardActor')) {
    function inlineGuardActor(): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo(['request-management.viewAny', 'request-management.update', 'request-management.viewAll']);

        return $actor;
    }
}

if (! function_exists('inlineGuardOffer')) {
    /**
     * An offer operated by $operator, at $site (null = no Sede at all) and
     * demanding exactly $category.
     */
    function inlineGuardOffer(User $operator, ?OperationalSite $site, ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        OpportunityProductLine::factory()->for($opportunity)->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create([
            'operator_id' => $operator->id,
            'operational_site_id' => $site?->id,
        ]);
    }
}

if (! function_exists('inlineGuardOperator')) {
    /** An operator employed at $site, competent for $category when one is given. */
    function inlineGuardOperator(OperationalSite $site, ?BusinessFunction $function = null, ?ProductCategory $category = null): User
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

if (! function_exists('inlineGuardPatch')) {
    /** The cell write under test. */
    function inlineGuardPatch(Quote $quote, User $operator): TestResponse
    {
        return test()->patchJson("/api/tables/request-management/rows/{$quote->id}", [
            'column' => 'operator_ga2',
            'value' => $operator->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// No Sede — the clause that distinguishes this path from the bulk.
// ---------------------------------------------------------------------------

it('inline: an offer with NO Sede accepts a competent operator, whatever Sede that operator belongs to', function () {
    $actor = inlineGuardActor();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $offer = inlineGuardOffer($actor, null, $category);

    // Employed somewhere else entirely: with no Sede on the offer there is no
    // Sede to match against, so the competence alone decides.
    $competent = inlineGuardOperator(OperationalSite::factory()->withAddress()->create(), $function, $category);
    Sanctum::actingAs($actor);

    inlineGuardPatch($offer, $competent)->assertOk();

    // The Sede stays null: the cell assigns an operator, it does not invent a
    // Sede for the offer.
    expect($offer->fresh()->operator_id)->toBe($competent->id)
        ->and($offer->fresh()->operational_site_id)->toBeNull();
});

it('inline: an offer with NO Sede still refuses an operator not competent for its categories', function () {
    $actor = inlineGuardActor();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $offer = inlineGuardOffer($actor, null, $category);

    $otherFunction = BusinessFunction::factory()->create();
    $otherCategory = ProductCategory::factory()->create(['business_function_id' => $otherFunction->id]);
    $notCompetent = inlineGuardOperator(OperationalSite::factory()->withAddress()->create(), $otherFunction, $otherCategory);
    Sanctum::actingAs($actor);

    inlineGuardPatch($offer, $notCompetent)->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    // Missing Sede relaxes the Sede half only. The offer keeps its operator.
    expect($offer->fresh()->operator_id)->toBe($actor->id);
});

// ---------------------------------------------------------------------------
// With a Sede — the half that would otherwise have no coverage on this path.
// ---------------------------------------------------------------------------

it('inline: an offer WITH a Sede refuses a competent operator who belongs to another one', function () {
    $actor = inlineGuardActor();
    $offerSite = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $offer = inlineGuardOffer($actor, $offerSite, $category);

    // Competent for exactly what the offer demands, at the wrong Sede — the
    // very pair the previous test lets through when the offer has no Sede.
    $elsewhere = inlineGuardOperator(OperationalSite::factory()->withAddress()->create(), $function, $category);
    Sanctum::actingAs($actor);

    inlineGuardPatch($offer, $elsewhere)->assertStatus(422)->assertJsonValidationErrors(['operator_id']);

    expect($offer->fresh()->operator_id)->toBe($actor->id);
});

it('inline: an offer WITH a Sede accepts an operator of that Sede competent for its categories', function () {
    $actor = inlineGuardActor();
    $offerSite = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $offer = inlineGuardOffer($actor, $offerSite, $category);

    $eligible = inlineGuardOperator($offerSite, $function, $category);
    Sanctum::actingAs($actor);

    inlineGuardPatch($offer, $eligible)->assertOk();

    expect($offer->fresh()->operator_id)->toBe($eligible->id);
});
