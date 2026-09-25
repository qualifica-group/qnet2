<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0168, AC-005..AC-009 (leads half) — POST /api/leads/assign-operators'
 * `operators_by_site`: restricts the balanced pool of a lead to the
 * operators left selected for ITS OWN campaign Sede, without touching the
 * Sede ∩ competence composition itself (AssignmentCandidates, untouched).
 */
if (! function_exists('balancedPoolActor')) {
    function balancedPoolActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.update');

        return $actor;
    }
}

if (! function_exists('balancedPoolCampaign')) {
    function balancedPoolCampaign(OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site->id]);
    }
}

if (! function_exists('balancedPoolOperator')) {
    /**
     * An operator employed at every one of $sites (first PHYSICAL, the rest
     * REMOTE), competent for every one of $campaigns' own product line.
     *
     * @param  array<int, OperationalSite>  $sites
     * @param  array<int, Campaign>  $campaigns
     */
    function balancedPoolOperator(array $sites, array $campaigns): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($sites[0]);

        if (count($sites) > 1) {
            $factory = $factory->remoteSites(...array_slice($sites, 1));
        }

        foreach ($campaigns as $campaign) {
            $line = $campaign->productLines()->firstOrFail();
            $factory = $factory->competentIn(
                BusinessFunction::query()->findOrFail($line->business_function_id),
                ProductCategory::query()->findOrFail($line->product_category_id),
            );
        }

        $factory->create();

        return $operator;
    }
}

// ---------------------------------------------------------------------------
// AC-005 — excluding an operator narrows the pool; the rest stays balanced.
// ---------------------------------------------------------------------------

it('0168 AC-005: excluding an operator of a Sede sends none of that Sede leads to them', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = balancedPoolCampaign($site);
    $excluded = balancedPoolOperator([$site], [$campaign]);
    $kept = balancedPoolOperator([$site], [$campaign]);

    $firstLead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $secondLead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$firstLead->id, $secondLead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$kept->id]],
        ],
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstLead->fresh()->operator_id)->toBe($kept->id)
        ->and($secondLead->fresh()->operator_id)->toBe($kept->id)
        ->and([$firstLead->fresh()->operator_id, $secondLead->fresh()->operator_id])
        ->not->toContain($excluded->id);
});

// ---------------------------------------------------------------------------
// AC-006 — D-2: per-group selection, an operator of two Sedi included in one.
// ---------------------------------------------------------------------------

it('0168 AC-006: an operator included for one Sede and excluded for another only receives the included one', function () {
    $actor = balancedPoolActor();
    $napoli = OperationalSite::factory()->withAddress()->create();
    $roma = OperationalSite::factory()->withAddress()->create();
    $napoliCampaign = balancedPoolCampaign($napoli);
    $romaCampaign = balancedPoolCampaign($roma);

    // Member of both, competent for both — a fallback that MUST take the
    // Roma lead only because Napoli's list excludes them.
    $shared = balancedPoolOperator([$napoli, $roma], [$napoliCampaign, $romaCampaign]);
    $napoliOnly = balancedPoolOperator([$napoli], [$napoliCampaign]);

    $napoliLead = Lead::factory()->create(['campaign_id' => $napoliCampaign->id]);
    $romaLead = Lead::factory()->create(['campaign_id' => $romaCampaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$napoliLead->id, $romaLead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $napoli->id, 'operator_ids' => [$napoliOnly->id]],
            ['operational_site_id' => $roma->id, 'operator_ids' => [$shared->id]],
        ],
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($napoliLead->fresh()->operator_id)->toBe($napoliOnly->id)
        ->and($romaLead->fresh()->operator_id)->toBe($shared->id);
});

// ---------------------------------------------------------------------------
// AC-007 — a Sede absent from the list: skipped, left untouched.
// ---------------------------------------------------------------------------

it('0168 AC-007: a Sede missing from operators_by_site skips its leads and leaves them untouched', function () {
    $actor = balancedPoolActor();
    $listed = OperationalSite::factory()->withAddress()->create();
    $unlisted = OperationalSite::factory()->withAddress()->create();
    $listedCampaign = balancedPoolCampaign($listed);
    $unlistedCampaign = balancedPoolCampaign($unlisted);

    $listedOperator = balancedPoolOperator([$listed], [$listedCampaign]);
    balancedPoolOperator([$unlisted], [$unlistedCampaign]);
    $previousOperator = User::factory()->create();

    $listedLead = Lead::factory()->create(['campaign_id' => $listedCampaign->id]);
    $unlistedLead = Lead::factory()->create([
        'campaign_id' => $unlistedCampaign->id,
        'operator_id' => $previousOperator->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$listedLead->id, $unlistedLead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $listed->id, 'operator_ids' => [$listedOperator->id]],
        ],
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    expect($listedLead->fresh()->operator_id)->toBe($listedOperator->id)
        ->and($unlistedLead->fresh()->operator_id)->toBe($previousOperator->id);
});

// ---------------------------------------------------------------------------
// AC-008 — an operator sent but not a candidate is ignored, never a 422.
// ---------------------------------------------------------------------------

it('0168 AC-008: an operator sent for a Sede they are no candidate of is silently ignored, no 422', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $elsewhere = OperationalSite::factory()->withAddress()->create();
    $campaign = balancedPoolCampaign($site);

    $candidate = balancedPoolOperator([$site], [$campaign]);
    // A real user, but employed elsewhere: not a candidate of $site at all.
    $notCandidate = balancedPoolOperator([$elsewhere], [balancedPoolCampaign($elsewhere)]);

    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$candidate->id, $notCandidate->id]],
        ],
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($lead->fresh()->operator_id)->toBe($candidate->id);
});

// ---------------------------------------------------------------------------
// AC-009 — validation.
// ---------------------------------------------------------------------------

it('0168 AC-009: operators_by_site with mode=single is 422', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = balancedPoolCampaign($site);
    $operator = balancedPoolOperator([$site], [$campaign]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site');
});

it('0168 AC-009: an empty operator_ids array is 422', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create(['campaign_id' => balancedPoolCampaign($site)->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => []],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operator_ids');
});

it('0168 AC-009: a duplicated Sede is 422', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = User::factory()->create();
    $lead = Lead::factory()->create(['campaign_id' => balancedPoolCampaign($site)->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operational_site_id');
});

it('0168 AC-009: a non-existent Sede or operator id is 422', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create(['campaign_id' => balancedPoolCampaign($site)->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => 999999, 'operator_ids' => [999999]],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['operators_by_site.0.operational_site_id', 'operators_by_site.0.operator_ids.0']);
});

it('0168 AC-009: without operators_by_site the current unrestricted behaviour is unchanged', function () {
    $actor = balancedPoolActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = balancedPoolCampaign($site);
    $operator = balancedPoolOperator([$site], [$campaign]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($lead->fresh()->operator_id)->toBe($operator->id);
});
