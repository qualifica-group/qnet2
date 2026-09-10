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
 * Spec 0048 (B): POST /api/leads/assign-operators — bulk-assign an Operatore
 * to many REAL leads at once, either to one chosen operator (mode=single) or
 * load-balanced across the operators of each lead's Sede (mode=balanced,
 * business-rule br-balanced).
 *
 * Spec 0113 rewrote what "the Sede" means on this endpoint: the caller no
 * longer sends one, it is derived from the campaign of each lead (D-3). Every
 * request below therefore omits `operational_site_id` — the key is now
 * `prohibited` (AC-021) — and the fixtures pin the Sede on the campaign
 * instead. Cross-campaign scoping lives in LeadAssignOperatorsSiteScopeTest.
 */
if (! function_exists('leadAssignActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadAssignActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('leadAssignCampaignAt')) {
    /**
     * A campaign whose Sede is $site: since spec 0113 this — not the request
     * payload — is what scopes the assignment of every lead created under it.
     */
    function leadAssignCampaignAt(OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site->id]);
    }
}

if (! function_exists('leadAssignOperatorFor')) {
    /**
     * An operator employed at $site — PHYSICAL by default, REMOTE when
     * $isPrimary is false (spec 0103, D-1) — and competent for the single
     * product line $campaign carries.
     *
     * The competence half is not decoration: a lead with no "Prodotti di
     * interesse" inherits its campaign's categories as its requirement
     * (LeadCompetence), and since spec 0111 rev.2 (D-9) a rowless profile is
     * competent for nothing, so a Sede membership alone would never make
     * this operator a candidate.
     */
    function leadAssignOperatorFor(Campaign $campaign, OperationalSite $site, bool $isPrimary = true): User
    {
        $operator = User::factory()->create();
        $line = $campaign->productLines()->firstOrFail();

        $factory = EmploymentProfile::factory()->for($operator)->competentIn(
            BusinessFunction::query()->findOrFail($line->business_function_id),
            ProductCategory::query()->findOrFail($line->product_category_id),
        );

        ($isPrimary ? $factory->physicalSite($site) : $factory->remoteSites($site))->create();

        return $operator;
    }
}

if (! function_exists('leadAssignLeadOf')) {
    function leadAssignLeadOf(Campaign $campaign): Lead
    {
        return Lead::factory()->create(['campaign_id' => $campaign->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-010 — mode=single
// ---------------------------------------------------------------------------

it('AC-010: mode=single assigns operator_id and the campaign Sede to every targeted lead', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $operator = leadAssignOperatorFor($campaign, $site);
    $lead1 = leadAssignLeadOf($campaign);
    $lead2 = leadAssignLeadOf($campaign);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead1->id, $lead2->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($lead1->fresh()->operator_id)->toBe($operator->id)
        ->and($lead1->fresh()->operational_site_id)->toBe($site->id)
        ->and($lead2->fresh()->operator_id)->toBe($operator->id)
        ->and($lead2->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-011 — mode=balanced
// ---------------------------------------------------------------------------

it('AC-011: mode=balanced spreads leads evenly across the Sede\'s operators when loads start equal', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $operatorA = leadAssignOperatorFor($campaign, $site);
    $operatorB = leadAssignOperatorFor($campaign, $site);
    $leads = collect(range(1, 4))->map(fn (): Lead => leadAssignLeadOf($campaign));
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $leads->pluck('id')->all(),
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 4);

    $countA = Lead::where('operator_id', $operatorA->id)->count();
    $countB = Lead::where('operator_id', $operatorB->id)->count();

    expect($countA + $countB)->toBe(4)
        ->and(abs($countA - $countB))->toBeLessThanOrEqual(1);
    foreach ($leads as $lead) {
        expect($lead->fresh()->operational_site_id)->toBe($site->id);
    }
});

it('AC-011: mode=balanced respects pre-existing load, filling the least-loaded operator first', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $busyOperator = leadAssignOperatorFor($campaign, $site);
    $idleOperator = leadAssignOperatorFor($campaign, $site);
    Lead::factory()->count(3)->create(['operator_id' => $busyOperator->id]);
    $newLeads = collect(range(1, 2))->map(fn (): Lead => leadAssignLeadOf($campaign));
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $newLeads->pluck('id')->all(),
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    // Both new leads go to the idle operator (0 vs 3): final load 3 vs 2,
    // difference <= 1 as required by br-balanced.
    expect(Lead::where('operator_id', $idleOperator->id)->count())->toBe(2)
        ->and(Lead::where('operator_id', $busyOperator->id)->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// AC-012/AC-013 (spec 0113) — a Sede with zero operators is no longer a 422
// ---------------------------------------------------------------------------

it('0113 AC-013: mode=balanced on a campaign Sede with zero operators is 200 {0, N}, not 422', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $lead = leadAssignLeadOf($campaign);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 1);

    // The Sede is derived per record, so "nobody at this Sede" is a per-lead
    // condition that lands in `skipped`. The derived Sede is still written.
    expect($lead->fresh()->operator_id)->toBeNull()
        ->and($lead->fresh()->operational_site_id)->toBe($site->id);
});

it('AC-012/D-1: mode=balanced on a Sede whose only operator has it as REMOTE distributes leads', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $remoteOperator = leadAssignOperatorFor($campaign, $site, isPrimary: false);
    $lead = leadAssignLeadOf($campaign);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($lead->fresh()->operator_id)->toBe($remoteOperator->id)
        ->and($lead->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-013 — authz + validation
// ---------------------------------------------------------------------------

it('AC-013: 403 without leads.update, nothing modified', function () {
    $actor = leadAssignActor([]);
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadAssignCampaignAt($site);
    $operator = leadAssignOperatorFor($campaign, $site);
    $lead = leadAssignLeadOf($campaign);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertForbidden();

    expect($lead->fresh()->operator_id)->toBeNull()
        ->and($lead->fresh()->operational_site_id)->toBeNull();
});

it('AC-013: 422 when lead_ids is empty', function () {
    $actor = leadAssignActor(['update']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [],
        'mode' => 'single',
        'operator_id' => User::factory()->create()->id,
    ])->assertStatus(422)->assertJsonValidationErrors('lead_ids');
});

it('AC-013: 422 when mode=single and operator_id is missing', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = leadAssignLeadOf(leadAssignCampaignAt($site));
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'single',
    ])->assertStatus(422)->assertJsonValidationErrors('operator_id');
});

it('AC-013: 422 when mode is not single or balanced', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = leadAssignLeadOf(leadAssignCampaignAt($site));
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'nonsense',
    ])->assertStatus(422)->assertJsonValidationErrors('mode');
});
