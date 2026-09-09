<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\CampaignProductLine;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0110 — PATCH /api/imports/{domain}/{importRun}/rows/assign becomes
 * competence-aware on `mode=balanced`: the Sede stays the outer filter, the
 * competence narrows it row by row, and the rows nobody is competent for come
 * back as `skipped`. AC-020, AC-021, AC-022, AC-023, AC-025.
 *
 * The `mode` contract itself (single/balanced, the 422s, the bulk
 * `product_ids`) stays covered by ImportBulkAssignBalancedTest.
 */
if (! function_exists('competenceImportActor')) {
    function competenceImportActor(): User
    {
        Permission::findOrCreate('leads.import');

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.import');
        grantImportRunsPermissions($actor, ['update']);

        return $actor;
    }
}

if (! function_exists('competenceImportOperator')) {
    /**
     * An operator employed at $site, carrying one competence row per
     * category, all paired with $function (spec 0111 D-2). Called without a
     * function the profile stays rowless: the wildcard state of INV-4b —
     * what every user looks like before anyone configures a competence
     * (AC-025).
     */
    function competenceImportOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
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

if (! function_exists('competenceImportRun')) {
    function competenceImportRun(User $actor): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $actor->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
        ]);
    }
}

/** A product under a category owning its own business function (spec 0023). */
if (! function_exists('competenceImportProduct')) {
    function competenceImportProduct(ProductCategory $category): Product
    {
        return Product::factory()->create(['category_id' => $category->id]);
    }
}

/**
 * A campaign classified with exactly $category. CampaignFactory creates a
 * product line of its own (spec 0094, D-1), dropped first so the coverage
 * check sees only this one.
 */
if (! function_exists('competenceImportCampaign')) {
    function competenceImportCampaign(ProductCategory $category): Campaign
    {
        $campaign = Campaign::factory()->create();
        $campaign->productLines()->delete();

        CampaignProductLine::factory()->create([
            'campaign_id' => $campaign->id,
            'product_category_id' => $category->id,
        ]);

        return $campaign;
    }
}

// ---------------------------------------------------------------------------
// AC-020 — each row reaches an operator competent for THAT row, balanced
// inside its own candidate pool.
// ---------------------------------------------------------------------------

it('0110 AC-020: mode=balanced sends every row to an operator competent for that row, balancing inside each pool', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $salesFunction = BusinessFunction::factory()->create();
    $serviceFunction = BusinessFunction::factory()->create();
    $salesCategory = ProductCategory::factory()->create(['business_function_id' => $salesFunction->id]);
    $serviceCategory = ProductCategory::factory()->create(['business_function_id' => $serviceFunction->id]);

    $firstSalesOperator = competenceImportOperator($site, $salesFunction, $salesCategory);
    $secondSalesOperator = competenceImportOperator($site, $salesFunction, $salesCategory);
    $serviceOperator = competenceImportOperator($site, $serviceFunction, $serviceCategory);

    $salesProduct = competenceImportProduct($salesCategory);
    $serviceProduct = competenceImportProduct($serviceCategory);

    $run = competenceImportRun($actor);
    $salesRowOne = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$salesProduct->id]]);
    $salesRowTwo = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 2, 'product_ids' => [$salesProduct->id]]);
    $serviceRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 3, 'product_ids' => [$serviceProduct->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$salesRowOne->id, $salesRowTwo->id, $serviceRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 3)
        ->assertJsonPath('data.skipped', 0);

    // The two sales rows split between the two sales operators (br-balanced
    // inside their own pool, lowest id first); the service row never reaches
    // them, competent or idle as they are.
    expect($salesRowOne->fresh()->operator_id)->toBe($firstSalesOperator->id)
        ->and($salesRowTwo->fresh()->operator_id)->toBe($secondSalesOperator->id)
        ->and($serviceRow->fresh()->operator_id)->toBe($serviceOperator->id)
        ->and($serviceRow->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-020 — the bulk `product_ids` of the SAME call define the requirement.
// ---------------------------------------------------------------------------

it('0110 AC-020: a bulk product_ids override drives the competence of the very call that writes it', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $salesFunction = BusinessFunction::factory()->create();
    $serviceFunction = BusinessFunction::factory()->create();
    $salesCategory = ProductCategory::factory()->create(['business_function_id' => $salesFunction->id]);
    $serviceCategory = ProductCategory::factory()->create(['business_function_id' => $serviceFunction->id]);

    $salesOperator = competenceImportOperator($site, $salesFunction, $salesCategory);
    competenceImportOperator($site, $serviceFunction, $serviceCategory);

    $salesProduct = competenceImportProduct($salesCategory);
    $serviceProduct = competenceImportProduct($serviceCategory);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'global_config' => ['campaign_id' => competenceImportCampaign($salesCategory)->id],
    ]);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$serviceProduct->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'product_ids' => [$salesProduct->id],
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 0);

    // The row leaves this call carrying the sales product, so the sales
    // operator is the one it had to reach — not the service operator its
    // pre-call override pointed at.
    expect($row->fresh()->product_ids)->toBe([$salesProduct->id])
        ->and($row->fresh()->operator_id)->toBe($salesOperator->id);
});

// ---------------------------------------------------------------------------
// AC-021 — a row nobody is competent for is skipped, not failed.
// ---------------------------------------------------------------------------

it('0110 AC-021: a row with no competent operator is skipped, keeps no operator, still receives the Sede', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $coveredFunction = BusinessFunction::factory()->create();
    $orphanFunction = BusinessFunction::factory()->create();
    $coveredCategory = ProductCategory::factory()->create(['business_function_id' => $coveredFunction->id]);
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $operator = competenceImportOperator($site, $coveredFunction, $coveredCategory);

    $run = competenceImportRun($actor);
    $coveredRow = ImportRunRow::factory()->for($run, 'importRun')->create([
        'row_number' => 1,
        'product_ids' => [competenceImportProduct($coveredCategory)->id],
    ]);
    $orphanRow = ImportRunRow::factory()->for($run, 'importRun')->create([
        'row_number' => 2,
        'product_ids' => [competenceImportProduct($orphanCategory)->id],
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$coveredRow->id, $orphanRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 1);

    // No all-or-nothing: the covered row is assigned regardless, and the
    // skipped one is still moved to the Sede.
    expect($coveredRow->fresh()->operator_id)->toBe($operator->id)
        ->and($orphanRow->fresh()->operator_id)->toBeNull()
        ->and($orphanRow->fresh()->operational_site_id)->toBe($site->id)
        ->and($orphanRow->fresh()->is_edited)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-022 — a Sede with no operators AT ALL is still the pre-feature 422.
// ---------------------------------------------------------------------------

it('0110 AC-022: mode=balanced on a Sede with zero operators stays a 422 and writes nothing', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $run = competenceImportRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$row->id],
    ])->assertStatus(422);

    expect($row->fresh()->operator_id)->toBeNull()
        ->and($row->fresh()->operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-023 — `single` never checks competence (user decision R-1).
// ---------------------------------------------------------------------------

it('0110 AC-023: mode=single assigns an operator who is NOT competent and reports skipped 0', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $operatorFunction = BusinessFunction::factory()->create();
    $rowFunction = BusinessFunction::factory()->create();
    $operatorCategory = ProductCategory::factory()->create(['business_function_id' => $operatorFunction->id]);
    $rowCategory = ProductCategory::factory()->create(['business_function_id' => $rowFunction->id]);

    $incompetentOperator = competenceImportOperator($site, $operatorFunction, $operatorCategory);

    $run = competenceImportRun($actor);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create([
        'row_number' => 1,
        'product_ids' => [competenceImportProduct($rowCategory)->id],
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'single',
        'operator_id' => $incompetentOperator->id,
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 0);

    expect($row->fresh()->operator_id)->toBe($incompetentOperator->id);
});

// ---------------------------------------------------------------------------
// AC-025 — nobody configured a competence: the pre-feature distribution.
// ---------------------------------------------------------------------------

it('0110 AC-025: with no competence configured anywhere the distribution is the pre-feature one and skipped is 0', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $firstOperator = competenceImportOperator($site);
    $secondOperator = competenceImportOperator($site);

    // The rows DO express a requirement: it is the operators being wildcards
    // (INV-4b) that must keep every one of them a candidate.
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = competenceImportProduct($category);

    $run = competenceImportRun($actor);
    $firstRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$product->id]]);
    $secondRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 2, 'product_ids' => [$product->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'operational_site_id' => $site->id,
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstRow->fresh()->operator_id)->toBe($firstOperator->id)
        ->and($secondRow->fresh()->operator_id)->toBe($secondOperator->id);
});
