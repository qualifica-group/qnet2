<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0110, AC-024 (leads half) — POST /api/leads/assign-operators applies
 * the SAME competence narrowing as the import wizard on `mode=balanced`, and
 * reports the leads it left behind in `skipped`. Spec 0111 AC-028 rev.2 is
 * asserted here too: the revoked jolly deroga (D-9) reaches this surface
 * through OperatorCompetence alone, without LeadAssignmentService changing.
 *
 * The `mode` contract itself (single/balanced, the 422s) stays covered by
 * LeadAssignOperatorsTest.
 *
 * Spec 0113: the requests below no longer send `operational_site_id` (the key
 * is `prohibited`, AC-021) — the Sede is derived from the campaign of each
 * lead (D-3), so the fixtures pin it there through leadInterestedIn().
 */
if (! function_exists('leadCompetenceActor')) {
    function leadCompetenceActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.update');

        return $actor;
    }
}

if (! function_exists('leadCompetenceOperator')) {
    /**
     * An operator employed at $site, carrying one competence row per
     * category, all paired with $function (spec 0111 D-2). Called without a
     * function the profile stays rowless: since rev.2 (D-9) not a candidate.
     */
    function leadCompetenceOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($site);

        if ($function !== null) {
            $factory = $factory->competentIn($function, ...$categories);
        }

        $factory->create();

        return $operator;
    }
}

/**
 * A lead whose "Prodotti di interesse" pin its requirement to $category and
 * whose campaign pins its Sede to $site (spec 0113, D-3).
 */
if (! function_exists('leadInterestedIn')) {
    function leadInterestedIn(OperationalSite $site, ProductCategory $category): Lead
    {
        $campaign = Campaign::factory()->create(['operational_site_id' => $site->id]);
        $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
        $lead->productsOfInterest()->sync([Product::factory()->create(['category_id' => $category->id])->id]);

        return $lead;
    }
}

// ---------------------------------------------------------------------------
// AC-024 (AC-020 on leads) — each lead reaches a competent operator.
// ---------------------------------------------------------------------------

it('0110 AC-024: mode=balanced sends every lead to an operator competent for that lead', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $salesFunction = BusinessFunction::factory()->create();
    $serviceFunction = BusinessFunction::factory()->create();
    $salesCategory = ProductCategory::factory()->create(['business_function_id' => $salesFunction->id]);
    $serviceCategory = ProductCategory::factory()->create(['business_function_id' => $serviceFunction->id]);

    $firstSalesOperator = leadCompetenceOperator($site, $salesFunction, $salesCategory);
    $secondSalesOperator = leadCompetenceOperator($site, $salesFunction, $salesCategory);
    $serviceOperator = leadCompetenceOperator($site, $serviceFunction, $serviceCategory);

    $salesLeadOne = leadInterestedIn($site, $salesCategory);
    $salesLeadTwo = leadInterestedIn($site, $salesCategory);
    $serviceLead = leadInterestedIn($site, $serviceCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$salesLeadOne->id, $salesLeadTwo->id, $serviceLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 3)
        ->assertJsonPath('data.skipped', 0);

    expect($salesLeadOne->fresh()->operator_id)->toBe($firstSalesOperator->id)
        ->and($salesLeadTwo->fresh()->operator_id)->toBe($secondSalesOperator->id)
        ->and($serviceLead->fresh()->operator_id)->toBe($serviceOperator->id);
});

// ---------------------------------------------------------------------------
// AC-024 (AC-021 on leads) — the uncovered lead is skipped, not failed.
// ---------------------------------------------------------------------------

it('0110 AC-024: a lead with no competent operator is skipped, keeps no operator and still receives the Sede', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $coveredFunction = BusinessFunction::factory()->create();
    $orphanFunction = BusinessFunction::factory()->create();
    $coveredCategory = ProductCategory::factory()->create(['business_function_id' => $coveredFunction->id]);
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $operator = leadCompetenceOperator($site, $coveredFunction, $coveredCategory);

    $coveredLead = leadInterestedIn($site, $coveredCategory);
    $orphanLead = leadInterestedIn($site, $orphanCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$coveredLead->id, $orphanLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    expect($coveredLead->fresh()->operator_id)->toBe($operator->id)
        ->and($orphanLead->fresh()->operator_id)->toBeNull()
        ->and($orphanLead->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-023 — `single` stays competence-blind on leads too.
// ---------------------------------------------------------------------------

it('0110 AC-023: mode=single assigns a non-competent operator to every lead and reports skipped 0', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $operatorFunction = BusinessFunction::factory()->create();
    $leadFunction = BusinessFunction::factory()->create();
    $operatorCategory = ProductCategory::factory()->create(['business_function_id' => $operatorFunction->id]);
    $leadCategory = ProductCategory::factory()->create(['business_function_id' => $leadFunction->id]);

    $incompetentOperator = leadCompetenceOperator($site, $operatorFunction, $operatorCategory);
    $lead = leadInterestedIn($site, $leadCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'single',
        'operator_id' => $incompetentOperator->id,
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 0);

    expect($lead->fresh()->operator_id)->toBe($incompetentOperator->id);
});

// ---------------------------------------------------------------------------
// AC-028 rev.2 — nobody configured: nobody is a candidate. Inverts the old
// AC-025 on this surface, with LeadAssignmentService left untouched.
// ---------------------------------------------------------------------------

it('0111 AC-028 rev.2: with no competence configured anywhere no lead is assigned and all are skipped', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    leadCompetenceOperator($site);
    leadCompetenceOperator($site);

    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $firstLead = leadInterestedIn($site, $category);
    $secondLead = leadInterestedIn($site, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$firstLead->id, $secondLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 2);

    // Skipped, not a 422: the Sede HAS operators, none of them competent.
    // The Sede is still written on the leads.
    expect($firstLead->fresh()->operator_id)->toBeNull()
        ->and($secondLead->fresh()->operator_id)->toBeNull()
        ->and($firstLead->fresh()->operational_site_id)->toBe($site->id)
        ->and($secondLead->fresh()->operational_site_id)->toBe($site->id);
});

it('0111 AC-030: a covering operator still takes the leads, the rowless colleague at the same Sede takes none', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $covering = leadCompetenceOperator($site, $function, $category);
    $rowless = leadCompetenceOperator($site);

    $firstLead = leadInterestedIn($site, $category);
    $secondLead = leadInterestedIn($site, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$firstLead->id, $secondLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect([$firstLead->fresh()->operator_id, $secondLead->fresh()->operator_id])
        ->toBe([$covering->id, $covering->id])
        ->not->toContain($rowless->id);
});
