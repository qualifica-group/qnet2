<?php

use App\Models\BusinessFunction;
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
 * reports the leads it left behind in `skipped`. AC-025's non-regression is
 * asserted here too: with nobody configured, the distribution is the
 * pre-feature one.
 *
 * The `mode` contract itself (single/balanced, the 422s) stays covered by
 * LeadAssignOperatorsTest.
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
     * An operator employed at $site, competent in $categories under
     * $function. No function and no category is the wildcard of INV-4b.
     */
    function leadCompetenceOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $operator = User::factory()->create();

        EmploymentProfile::factory()
            ->for($operator)
            ->physicalSite($site)
            ->competentIn(...$categories)
            ->create(['business_function_id' => $function?->id]);

        return $operator;
    }
}

/** A lead whose "Prodotti di interesse" pin its requirement to $category. */
if (! function_exists('leadInterestedIn')) {
    function leadInterestedIn(ProductCategory $category): Lead
    {
        $lead = Lead::factory()->create();
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

    $salesLeadOne = leadInterestedIn($salesCategory);
    $salesLeadTwo = leadInterestedIn($salesCategory);
    $serviceLead = leadInterestedIn($serviceCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$salesLeadOne->id, $salesLeadTwo->id, $serviceLead->id],
        'operational_site_id' => $site->id,
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

    $coveredLead = leadInterestedIn($coveredCategory);
    $orphanLead = leadInterestedIn($orphanCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$coveredLead->id, $orphanLead->id],
        'operational_site_id' => $site->id,
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
    $lead = leadInterestedIn($leadCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $incompetentOperator->id,
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 0);

    expect($lead->fresh()->operator_id)->toBe($incompetentOperator->id);
});

// ---------------------------------------------------------------------------
// AC-025 — nobody configured: the pre-feature distribution, skipped 0.
// ---------------------------------------------------------------------------

it('0110 AC-025: with no competence configured anywhere the leads distribution is the pre-feature one', function () {
    $actor = leadCompetenceActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $firstOperator = leadCompetenceOperator($site);
    $secondOperator = leadCompetenceOperator($site);

    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $firstLead = leadInterestedIn($category);
    $secondLead = leadInterestedIn($category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$firstLead->id, $secondLead->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstLead->fresh()->operator_id)->toBe($firstOperator->id)
        ->and($secondLead->fresh()->operator_id)->toBe($secondOperator->id);
});
