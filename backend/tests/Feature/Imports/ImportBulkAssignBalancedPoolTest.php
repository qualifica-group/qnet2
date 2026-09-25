<?php

use App\Enums\ImportStatus;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0168, AC-005..AC-010 (import half) — PATCH
 * /api/imports/{domain}/{importRun}/rows/assign's `operators_by_site`:
 * restricts the balanced pool of a row to the operators left selected for
 * ITS OWN campaign Sede. `updated` replaces `assigned` in this endpoint's
 * envelope (AC-010).
 */
if (! function_exists('importBalancedPoolActor')) {
    function importBalancedPoolActor(): User
    {
        Permission::findOrCreate('leads.import');

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.import');
        grantImportRunsPermissions($actor, ['update']);

        return $actor;
    }
}

if (! function_exists('importBalancedPoolCampaign')) {
    function importBalancedPoolCampaign(OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site->id]);
    }
}

if (! function_exists('importBalancedPoolOperator')) {
    /**
     * @param  array<int, OperationalSite>  $sites
     * @param  array<int, Campaign>  $campaigns
     */
    function importBalancedPoolOperator(array $sites, array $campaigns): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($sites[0]);

        if (count($sites) > 1) {
            $factory = $factory->remoteSites(...array_slice($sites, 1));
        }

        foreach ($campaigns as $campaign) {
            $line = $campaign->productLines()->firstOrFail();
            $factory = $factory->competentIn($line->businessFunction, $line->productCategory);
        }

        $factory->create();

        return $operator;
    }
}

if (! function_exists('importBalancedPoolRun')) {
    function importBalancedPoolRun(User $actor): ImportRun
    {
        return ImportRun::factory()->create([
            'user_id' => $actor->id,
            'resource' => 'leads',
            'status' => ImportStatus::Reviewing,
            'global_config' => [],
        ]);
    }
}

if (! function_exists('importBalancedPoolRow')) {
    function importBalancedPoolRow(ImportRun $run, int $rowNumber, Campaign $campaign, ?User $operator = null): ImportRunRow
    {
        return ImportRunRow::factory()->for($run, 'importRun')->create([
            'row_number' => $rowNumber,
            'operator_id' => $operator?->id,
            'mapped_values' => ['email' => "row{$rowNumber}@example.com", 'campaign_id' => $campaign->id],
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-005 — excluding an operator narrows the pool.
// ---------------------------------------------------------------------------

it('0168 AC-005/AC-010: excluding an operator sends none of that Sede rows to them', function () {
    $actor = importBalancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = importBalancedPoolCampaign($site);
    $excluded = importBalancedPoolOperator([$site], [$campaign]);
    $kept = importBalancedPoolOperator([$site], [$campaign]);

    $run = importBalancedPoolRun($actor);
    $firstRow = importBalancedPoolRow($run, 1, $campaign);
    $secondRow = importBalancedPoolRow($run, 2, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$firstRow->id, $secondRow->id],
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$kept->id]],
        ],
    ])->assertOk()
        ->assertJsonPath('data.updated', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstRow->fresh()->operator_id)->toBe($kept->id)
        ->and($secondRow->fresh()->operator_id)->toBe($kept->id)
        ->and([$firstRow->fresh()->operator_id, $secondRow->fresh()->operator_id])
        ->not->toContain($excluded->id);
});

// ---------------------------------------------------------------------------
// AC-006 — D-2: per-group selection.
// ---------------------------------------------------------------------------

it('0168 AC-006/AC-010: an operator included for one Sede and excluded for another only receives the included one', function () {
    $actor = importBalancedPoolActor();
    $napoli = OperationalSite::factory()->withAddress()->create();
    $roma = OperationalSite::factory()->withAddress()->create();
    $napoliCampaign = importBalancedPoolCampaign($napoli);
    $romaCampaign = importBalancedPoolCampaign($roma);

    $shared = importBalancedPoolOperator([$napoli, $roma], [$napoliCampaign, $romaCampaign]);
    $napoliOnly = importBalancedPoolOperator([$napoli], [$napoliCampaign]);

    $run = importBalancedPoolRun($actor);
    $napoliRow = importBalancedPoolRow($run, 1, $napoliCampaign);
    $romaRow = importBalancedPoolRow($run, 2, $romaCampaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$napoliRow->id, $romaRow->id],
        'operators_by_site' => [
            ['operational_site_id' => $napoli->id, 'operator_ids' => [$napoliOnly->id]],
            ['operational_site_id' => $roma->id, 'operator_ids' => [$shared->id]],
        ],
    ])->assertOk()->assertJsonPath('data.updated', 2);

    expect($napoliRow->fresh()->operator_id)->toBe($napoliOnly->id)
        ->and($romaRow->fresh()->operator_id)->toBe($shared->id);
});

// ---------------------------------------------------------------------------
// AC-007 — a Sede absent from the list: skipped, untouched.
// ---------------------------------------------------------------------------

it('0168 AC-007/AC-010: a Sede missing from operators_by_site skips its rows and leaves the operator untouched', function () {
    $actor = importBalancedPoolActor();
    $listed = OperationalSite::factory()->withAddress()->create();
    $unlisted = OperationalSite::factory()->withAddress()->create();
    $listedCampaign = importBalancedPoolCampaign($listed);
    $unlistedCampaign = importBalancedPoolCampaign($unlisted);

    $listedOperator = importBalancedPoolOperator([$listed], [$listedCampaign]);
    importBalancedPoolOperator([$unlisted], [$unlistedCampaign]);
    $previousOperator = User::factory()->create();

    $run = importBalancedPoolRun($actor);
    $listedRow = importBalancedPoolRow($run, 1, $listedCampaign);
    $unlistedRow = importBalancedPoolRow($run, 2, $unlistedCampaign, $previousOperator);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$listedRow->id, $unlistedRow->id],
        'operators_by_site' => [
            ['operational_site_id' => $listed->id, 'operator_ids' => [$listedOperator->id]],
        ],
    ])->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 1);

    expect($listedRow->fresh()->operator_id)->toBe($listedOperator->id)
        ->and($unlistedRow->fresh()->operator_id)->toBe($previousOperator->id);
});

// ---------------------------------------------------------------------------
// AC-008 — an operator sent but not a candidate is ignored, never a 422.
// ---------------------------------------------------------------------------

it('0168 AC-008/AC-010: an operator sent for a Sede they are no candidate of is silently ignored, no 422', function () {
    $actor = importBalancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $elsewhere = OperationalSite::factory()->withAddress()->create();
    $campaign = importBalancedPoolCampaign($site);

    $candidate = importBalancedPoolOperator([$site], [$campaign]);
    $notCandidate = importBalancedPoolOperator([$elsewhere], [importBalancedPoolCampaign($elsewhere)]);

    $run = importBalancedPoolRun($actor);
    $row = importBalancedPoolRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$candidate->id, $notCandidate->id]],
        ],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($row->fresh()->operator_id)->toBe($candidate->id);
});

// ---------------------------------------------------------------------------
// AC-009 — validation.
// ---------------------------------------------------------------------------

it('0168 AC-009/AC-010: operators_by_site with mode=single (the import default) is 422', function () {
    $actor = importBalancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = importBalancedPoolCampaign($site);
    $operator = importBalancedPoolOperator([$site], [$campaign]);

    $run = importBalancedPoolRun($actor);
    $row = importBalancedPoolRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'operator_id' => $operator->id,
        'row_ids' => [$row->id],
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site');
});

it('0168 AC-009/AC-010: an empty operator_ids array, a duplicated Sede and a non-existent id are 422', function () {
    $actor = importBalancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = importBalancedPoolCampaign($site);
    $operator = importBalancedPoolOperator([$site], [$campaign]);

    $run = importBalancedPoolRun($actor);
    $row = importBalancedPoolRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => []],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operator_ids');

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operational_site_id');

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
        'operators_by_site' => [
            ['operational_site_id' => 999999, 'operator_ids' => [999999]],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['operators_by_site.0.operational_site_id', 'operators_by_site.0.operator_ids.0']);
});

it('0168 AC-009/AC-010: without operators_by_site the current unrestricted behaviour is unchanged', function () {
    $actor = importBalancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = importBalancedPoolCampaign($site);
    $operator = importBalancedPoolOperator([$site], [$campaign]);

    $run = importBalancedPoolRun($actor);
    $row = importBalancedPoolRow($run, 1, $campaign);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/assign", [
        'mode' => 'balanced',
        'row_ids' => [$row->id],
    ])->assertOk()->assertJsonPath('data.updated', 1);

    expect($row->fresh()->operator_id)->toBe($operator->id);
});
