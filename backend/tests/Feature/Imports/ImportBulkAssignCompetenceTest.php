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
 * back as `skipped`. AC-020, AC-021, AC-023, AC-025.
 *
 * Since spec 0113 that outer filter is DERIVED from each row's campaign
 * instead of being chosen by the caller, so the fixtures below put their Sede
 * on a campaign; the derivation itself is covered by
 * ImportBulkAssignCampaignSiteTest, and 0110's AC-022 is revoked there
 * (see the AC-013 case below).
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
     * function the profile stays rowless — what every user looks like before
     * anyone configures a competence, and since rev.2 (D-9) no longer a
     * candidate for anything (AC-027).
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

/**
 * A run whose GLOBAL campaign carries $site (spec 0113 D-1): since the Sede
 * of a row is derived from its campaign, that campaign is what puts these
 * rows inside $site. The campaign's own classification never becomes the
 * requirement here — every row below carries its own `product_ids`, which
 * takes precedence (INV-1).
 */
if (! function_exists('competenceImportRun')) {
    function competenceImportRun(User $actor, ?OperationalSite $site = null): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $actor->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
            'global_config' => $site === null
                ? []
                : ['campaign_id' => Campaign::factory()->create(['operational_site_id' => $site->id])->id],
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
    function competenceImportCampaign(ProductCategory $category, ?OperationalSite $site = null): Campaign
    {
        $campaign = Campaign::factory()->create(['operational_site_id' => $site?->id]);
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

    $run = competenceImportRun($actor, $site);
    $salesRowOne = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$salesProduct->id]]);
    $salesRowTwo = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 2, 'product_ids' => [$salesProduct->id]]);
    $serviceRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 3, 'product_ids' => [$serviceProduct->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
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
        'global_config' => ['campaign_id' => competenceImportCampaign($salesCategory, $site)->id],
    ]);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$serviceProduct->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
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

    $run = competenceImportRun($actor, $site);
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
// AC-022 is REVOKED by spec 0113 (AC-013): a Sede with no operators at all
// was an all-or-nothing 422 back when ONE Sede was chosen for the whole call.
// Now that each row derives its own, "nobody at that Sede" is a per-row
// condition like any other and confluences into `skipped`.
// ---------------------------------------------------------------------------

it('0113 AC-013: mode=balanced on a Sede with zero operators is 200 with the row skipped', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $run = competenceImportRun($actor, $site);
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', 1);

    expect($row->fresh()->operator_id)->toBeNull()
        ->and($row->fresh()->operational_site_id)->toBe($site->id);
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

    $run = competenceImportRun($actor, $site);
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
// AC-027 rev.2 — nobody configured a competence: nobody is a candidate.
// This INVERTS the old AC-025 (spec 0110 INV-4b), revoked by D-9 after the
// field test that assigned rows to operators without the required category.
// ---------------------------------------------------------------------------

it('0111 AC-027 rev.2: with no competence configured anywhere no row is assigned, all are skipped, Sede and products are still written', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    competenceImportOperator($site);
    competenceImportOperator($site);

    // The rows DO express a requirement, and no operator covers it: the Sede
    // stops being the only effective constraint.
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $product = competenceImportProduct($category);

    $run = competenceImportRun($actor, $site);
    $firstRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 1, 'product_ids' => [$product->id]]);
    $secondRow = ImportRunRow::factory()->for($run, 'importRun')->create(['row_number' => 2, 'product_ids' => [$product->id]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', 2);

    // A Sede WITH operators but none competent is `skipped`, never the 422
    // reserved for an empty Sede (AC-022): the rows keep the Sede and their
    // products.
    expect($firstRow->fresh()->operator_id)->toBeNull()
        ->and($secondRow->fresh()->operator_id)->toBeNull()
        ->and($firstRow->fresh()->operational_site_id)->toBe($site->id)
        ->and($secondRow->fresh()->operational_site_id)->toBe($site->id)
        ->and($firstRow->fresh()->product_ids)->toBe([$product->id]);
});

it('0111 AC-030: the covering operator still takes the rows, and the rowless colleague at the same Sede takes none', function () {
    $actor = competenceImportActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $covering = competenceImportOperator($site, $function, $category);
    // Employed at the same Sede, but the profile carries no row at all.
    $rowless = competenceImportOperator($site);

    $run = competenceImportRun($actor, $site);
    $rows = collect([1, 2])->map(fn (int $number) => ImportRunRow::factory()->for($run, 'importRun')->create([
        'row_number' => $number,
        'product_ids' => [competenceImportProduct($category)->id],
    ]));
    $rowIds = $rows->pluck('id')->all();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => $rowIds,
    ])->assertOk()
        ->assertJsonPath('data.updated', 2)
        ->assertJsonPath('data.skipped', 0);

    // The positive path is untouched (AC-011/AC-013): the covering operator
    // takes both rows instead of splitting them with the rowless one.
    expect($rows->map(fn ($row) => $row->fresh()->operator_id)->all())
        ->toBe([$covering->id, $covering->id])
        ->and($rows->map(fn ($row) => $row->fresh()->operator_id)->all())
        ->not->toContain($rowless->id);
});
