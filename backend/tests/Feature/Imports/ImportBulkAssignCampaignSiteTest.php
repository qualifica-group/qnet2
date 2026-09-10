<?php

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0113 — PATCH /api/imports/{domain}/{importRun}/rows/assign stops
 * taking a Sede from the caller and derives it from each row's own CAMPAIGN
 * (D-1), writing it back on the row so the Lead born at commit inherits it
 * (D-6/D-7). AC-010 to AC-017.
 *
 * The competence rule itself (spec 0110 rev.2 / 0111) is unchanged and stays
 * covered by ImportBulkAssignCompetenceTest; the `mode` contract by
 * ImportBulkAssignBalancedTest.
 */
if (! function_exists('campaignSiteActor')) {
    function campaignSiteActor(): User
    {
        Permission::findOrCreate('leads.import');

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.import');
        grantImportRunsPermissions($actor, ['update']);

        return $actor;
    }
}

/**
 * A campaign whose own `operational_site_id` is $site (null = a campaign
 * carrying no Sede, AC-003). CampaignFactory gives it ONE product line
 * (function + category), which is what its rows will demand of an operator.
 */
if (! function_exists('campaignSiteCampaign')) {
    function campaignSiteCampaign(?OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site?->id]);
    }
}

/**
 * An operator employed at every one of $sites (the first as PHYSICAL, the
 * rest as REMOTE — spec 0103 D-1 makes them equivalent here) and competent
 * for the classification of every one of $campaigns.
 *
 * @param  array<int, OperationalSite>  $sites
 * @param  array<int, Campaign>  $campaigns
 */
if (! function_exists('campaignSiteOperator')) {
    function campaignSiteOperator(array $sites, array $campaigns = []): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($sites[0]);

        if (count($sites) > 1) {
            $factory = $factory->remoteSites(...array_slice($sites, 1));
        }

        foreach ($campaigns as $campaign) {
            $productLine = $campaign->productLines()->first();
            $factory = $factory->competentIn($productLine->businessFunction, $productLine->productCategory);
        }

        $factory->create();

        return $operator;
    }
}

if (! function_exists('campaignSiteRun')) {
    function campaignSiteRun(User $actor, ?Campaign $globalCampaign = null): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $actor->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
            'global_config' => $globalCampaign === null ? [] : ['campaign_id' => $globalCampaign->id],
        ]);
    }
}

/** A staged row pinned to its own campaign (the per-row reading, spec 0108). */
if (! function_exists('campaignSiteRow')) {
    function campaignSiteRow(ImportRun $run, int $rowNumber, Campaign $campaign, ?User $operator = null): ImportRunRow
    {
        return ImportRunRow::factory()->for($run, 'importRun')->create([
            'row_number' => $rowNumber,
            'operator_id' => $operator?->id,
            'mapped_values' => ['email' => "row{$rowNumber}@example.com", 'campaign_id' => $campaign->id],
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-010 — the Sede of the row's own campaign scopes the pool.
// ---------------------------------------------------------------------------

it('0113 AC-010: rows of two campaigns each reach an operator of their OWN campaign Sede', function () {
    $actor = campaignSiteActor();
    $firstSite = OperationalSite::factory()->withAddress()->create();
    $secondSite = OperationalSite::factory()->withAddress()->create();

    $firstCampaign = campaignSiteCampaign($firstSite);
    $secondCampaign = campaignSiteCampaign($secondSite);

    $firstSiteOperator = campaignSiteOperator([$firstSite], [$firstCampaign]);
    $secondSiteOperator = campaignSiteOperator([$secondSite], [$secondCampaign]);
    // Competent for the FIRST campaign but employed at the SECOND Sede: the
    // competence alone must never be enough to receive that campaign's rows.
    $crossSiteOperator = campaignSiteOperator([$secondSite], [$firstCampaign]);

    $run = campaignSiteRun($actor);
    $firstRow = campaignSiteRow($run, 1, $firstCampaign);
    $secondRow = campaignSiteRow($run, 2, $secondCampaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstRow->fresh()->operator_id)->toBe($firstSiteOperator->id)
        ->and($secondRow->fresh()->operator_id)->toBe($secondSiteOperator->id)
        ->and([$firstRow->fresh()->operator_id, $secondRow->fresh()->operator_id])
        ->not->toContain($crossSiteOperator->id);
});

// ---------------------------------------------------------------------------
// AC-011 — ONE load map for the whole batch, tie-break on the lowest id.
// ---------------------------------------------------------------------------

it('0113 AC-011: the load map is shared across Sedi, so an operator working two Sedi carries their load into both', function () {
    $actor = campaignSiteActor();
    $firstSite = OperationalSite::factory()->withAddress()->create();
    $secondSite = OperationalSite::factory()->withAddress()->create();

    $firstCampaign = campaignSiteCampaign($firstSite);
    $secondCampaign = campaignSiteCampaign($secondSite);

    // Created FIRST, so the lowest id: a tie always resolves to them.
    $sharedOperator = campaignSiteOperator([$firstSite, $secondSite], [$firstCampaign, $secondCampaign]);
    $localOperator = campaignSiteOperator([$firstSite], [$firstCampaign]);
    Lead::factory()->create(['operator_id' => $localOperator->id]);

    $run = campaignSiteRun($actor);
    $firstRow = campaignSiteRow($run, 1, $firstCampaign);
    $secondRow = campaignSiteRow($run, 2, $secondCampaign);
    $thirdRow = campaignSiteRow($run, 3, $firstCampaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$firstRow->id, $secondRow->id, $thirdRow->id],
    ])->assertOk()->assertJsonPath('data.updated', 3);

    // Row 1: 0 vs 1 real lead -> the shared operator. Row 2: only they can
    // take it -> load 2. Row 3: the load they built on the OTHER Sede is
    // still on the same map, so the local operator (1) wins — with a map per
    // Sede this would have tied at 1 and gone back to the lowest id.
    expect($firstRow->fresh()->operator_id)->toBe($sharedOperator->id)
        ->and($secondRow->fresh()->operator_id)->toBe($sharedOperator->id)
        ->and($thirdRow->fresh()->operator_id)->toBe($localOperator->id);
});

it('0113 AC-011: two idle operators of the same Sede split the rows starting from the lowest id', function () {
    $actor = campaignSiteActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = campaignSiteCampaign($site);

    $lowerIdOperator = campaignSiteOperator([$site], [$campaign]);
    $higherIdOperator = campaignSiteOperator([$site], [$campaign]);

    $run = campaignSiteRun($actor, $campaign);
    $firstRow = campaignSiteRow($run, 1, $campaign);
    $secondRow = campaignSiteRow($run, 2, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    expect($firstRow->fresh()->operator_id)->toBe($lowerIdOperator->id)
        ->and($secondRow->fresh()->operator_id)->toBe($higherIdOperator->id);
});

// ---------------------------------------------------------------------------
// AC-012 / AC-013 — no candidate is `skipped`, never a failure.
// ---------------------------------------------------------------------------

it('0113 AC-012: a row whose campaign carries no Sede keeps the operator it had and is skipped', function () {
    $actor = campaignSiteActor();
    $site = OperationalSite::factory()->withAddress()->create();

    $coveredCampaign = campaignSiteCampaign($site);
    $sitelessCampaign = campaignSiteCampaign(null);

    $operator = campaignSiteOperator([$site], [$coveredCampaign, $sitelessCampaign]);
    $previousOperator = User::factory()->create();

    $run = campaignSiteRun($actor);
    $coveredRow = campaignSiteRow($run, 1, $coveredCampaign);
    $sitelessRow = campaignSiteRow($run, 2, $sitelessCampaign, $previousOperator);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$coveredRow->id, $sitelessRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 1);

    // Competent for it or not, nobody may take a row whose Sede is unknown;
    // and the row is left exactly as the operator had it.
    expect($coveredRow->fresh()->operator_id)->toBe($operator->id)
        ->and($sitelessRow->fresh()->operator_id)->toBe($previousOperator->id)
        ->and($sitelessRow->fresh()->operational_site_id)->toBeNull()
        ->and($sitelessRow->fresh()->is_edited)->toBeTrue();
});

it('0113 AC-013: a Sede with no operator at all is 200 with everything skipped, not a 422', function () {
    $actor = campaignSiteActor();
    $emptySite = OperationalSite::factory()->withAddress()->create();
    $campaign = campaignSiteCampaign($emptySite);

    $run = campaignSiteRun($actor, $campaign);
    $rows = collect([1, 2])->map(fn (int $number): ImportRunRow => campaignSiteRow($run, $number, $campaign));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => $rows->pluck('id')->all(),
    ])->assertOk()
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', 2);

    // The Sede is derived per row now, so "nobody at that Sede" is a per-row
    // condition and confluences into `skipped`: the old all-or-nothing 422 is
    // gone.
    expect($rows->map(fn (ImportRunRow $row): ?int => $row->fresh()->operator_id)->all())->toBe([null, null])
        ->and($rows->first()->fresh()->operational_site_id)->toBe($emptySite->id);
});

// ---------------------------------------------------------------------------
// AC-014 — the derived Sede is WRITTEN, grouped per distinct Sede.
// ---------------------------------------------------------------------------

it('0113 AC-014: every assigned row receives its campaign Sede and is_edited, in one UPDATE per distinct Sede', function () {
    $actor = campaignSiteActor();
    $firstSite = OperationalSite::factory()->withAddress()->create();
    $secondSite = OperationalSite::factory()->withAddress()->create();

    $firstCampaign = campaignSiteCampaign($firstSite);
    $secondCampaign = campaignSiteCampaign($secondSite);
    campaignSiteOperator([$firstSite], [$firstCampaign]);
    campaignSiteOperator([$secondSite], [$secondCampaign]);

    $run = campaignSiteRun($actor);
    $firstRows = collect([1, 2])->map(fn (int $number): ImportRunRow => campaignSiteRow($run, $number, $firstCampaign));
    $secondRows = collect([3, 4])->map(fn (int $number): ImportRunRow => campaignSiteRow($run, $number, $secondCampaign));
    Sanctum::actingAs($actor);

    $siteWrites = 0;
    DB::listen(function ($query) use (&$siteWrites): void {
        if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'operational_site_id')) {
            $siteWrites++;
        }
    });

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [...$firstRows->pluck('id')->all(), ...$secondRows->pluck('id')->all()],
    ])->assertOk()->assertJsonPath('data.updated', 4);

    // Two Sedi, four rows, two statements: the write scales with the Sedi in
    // play, never with the rows.
    expect($siteWrites)->toBe(2)
        ->and($firstRows->map(fn (ImportRunRow $row): ?int => $row->fresh()->operational_site_id)->all())
        ->toBe([$firstSite->id, $firstSite->id])
        ->and($secondRows->map(fn (ImportRunRow $row): ?int => $row->fresh()->operational_site_id)->all())
        ->toBe([$secondSite->id, $secondSite->id])
        ->and($firstRows->every(fn (ImportRunRow $row): bool => $row->fresh()->is_edited))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-015 / AC-016 / AC-017 — the `single` branch and the frozen payload.
// ---------------------------------------------------------------------------

it('0113 AC-015: mode=single assigns the chosen operator to every row and writes the campaign Sede too', function () {
    $actor = campaignSiteActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = campaignSiteCampaign($site);

    // Deliberately NOT employed at the Sede and NOT competent: `single` runs
    // no check at all (spec 0110 R-1, unchanged).
    $chosenOperator = User::factory()->create();

    $run = campaignSiteRun($actor, $campaign);
    $firstRow = campaignSiteRow($run, 1, $campaign);
    $secondRow = campaignSiteRow($run, 2, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'single',
        'operator_id' => $chosenOperator->id,
        'row_ids' => [$firstRow->id, $secondRow->id],
    ])->assertOk()
        ->assertJsonPath('data.updated', 2)
        ->assertJsonPath('data.skipped', 0);

    // D-7: without the Sede here, a row assigned by hand would become a Lead
    // with no Sede at all.
    expect($firstRow->fresh()->operator_id)->toBe($chosenOperator->id)
        ->and($secondRow->fresh()->operator_id)->toBe($chosenOperator->id)
        ->and($firstRow->fresh()->operational_site_id)->toBe($site->id)
        ->and($secondRow->fresh()->operational_site_id)->toBe($site->id)
        ->and($firstRow->fresh()->is_edited)->toBeTrue();
});

it('0113 AC-016: a payload still carrying operational_site_id is rejected with a 422', function () {
    $actor = campaignSiteActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = campaignSiteCampaign($site);
    $operator = campaignSiteOperator([$site], [$campaign]);

    $run = campaignSiteRun($actor, $campaign);
    $row = campaignSiteRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'single',
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'row_ids' => [$row->id],
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    // Rejected, not silently ignored: nothing was written.
    expect($row->fresh()->operator_id)->toBeNull()
        ->and($row->fresh()->operational_site_id)->toBeNull();
});

it('0113 AC-017: a products-only call assigns the products and touches neither operator nor Sede', function () {
    $actor = campaignSiteActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = campaignSiteCampaign($site);
    $product = Product::factory()->create([
        'category_id' => $campaign->productLines()->first()->product_category_id,
    ]);

    $run = campaignSiteRun($actor, $campaign);
    $row = campaignSiteRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'product_ids' => [$product->id],
        'row_ids' => [$row->id],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($row->fresh()->product_ids)->toBe([$product->id])
        ->and($row->fresh()->is_edited)->toBeTrue()
        ->and($row->fresh()->operator_id)->toBeNull()
        ->and($row->fresh()->operational_site_id)->toBeNull();
});
