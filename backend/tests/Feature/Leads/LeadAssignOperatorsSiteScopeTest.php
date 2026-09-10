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
 * Spec 0113, AC-018/AC-021 (leads half): POST /api/leads/assign-operators no
 * longer takes a Sede. Each lead is scoped by the Sede of its OWN campaign
 * (D-3, `leads.campaign_id` -> `campaigns.operational_site_id`), never by
 * `leads.operational_site_id` and never by the payload, so one call may span
 * as many Sedi as the selection touches.
 *
 * The competence narrowing itself (spec 0110/0111) stays covered by
 * LeadAssignOperatorsCompetenceTest; what is asserted here is the Sede half
 * and its interaction with it.
 */
if (! function_exists('leadSiteScopeActor')) {
    function leadSiteScopeActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo('leads.update');

        return $actor;
    }
}

if (! function_exists('leadSiteScopeCampaign')) {
    /** A campaign at $site (null = a campaign carrying no Sede at all). */
    function leadSiteScopeCampaign(?OperationalSite $site): Campaign
    {
        return Campaign::factory()->create(['operational_site_id' => $site?->id]);
    }
}

if (! function_exists('leadSiteScopeOperator')) {
    /**
     * An operator employed at $site and competent for the single product
     * line $campaign carries — the requirement a lead of that campaign
     * inherits when it has no "Prodotti di interesse" of its own.
     */
    function leadSiteScopeOperator(Campaign $campaign, OperationalSite $site): User
    {
        $operator = User::factory()->create();
        $line = $campaign->productLines()->firstOrFail();

        EmploymentProfile::factory()
            ->for($operator)
            ->physicalSite($site)
            ->competentIn(
                BusinessFunction::query()->findOrFail($line->business_function_id),
                ProductCategory::query()->findOrFail($line->product_category_id),
            )
            ->create();

        return $operator;
    }
}

// ---------------------------------------------------------------------------
// AC-018 — every lead lands on its own campaign's Sede.
// ---------------------------------------------------------------------------

it('0113 AC-018: mode=balanced sends each lead to a competent operator of ITS OWN campaign Sede', function () {
    $actor = leadSiteScopeActor();
    $northSite = OperationalSite::factory()->withAddress()->create();
    $southSite = OperationalSite::factory()->withAddress()->create();
    $emptySite = OperationalSite::factory()->withAddress()->create();

    $northCampaign = leadSiteScopeCampaign($northSite);
    $southCampaign = leadSiteScopeCampaign($southSite);
    $uncoveredCampaign = leadSiteScopeCampaign($emptySite);

    $northOperator = leadSiteScopeOperator($northCampaign, $northSite);
    $southOperator = leadSiteScopeOperator($southCampaign, $southSite);
    $formerOperator = User::factory()->create();

    $northLead = Lead::factory()->create(['campaign_id' => $northCampaign->id]);
    $southLead = Lead::factory()->create(['campaign_id' => $southCampaign->id]);
    $uncoveredLead = Lead::factory()->create([
        'campaign_id' => $uncoveredCampaign->id,
        'operator_id' => $formerOperator->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$northLead->id, $southLead->id, $uncoveredLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 1);

    expect($northLead->fresh()->operator_id)->toBe($northOperator->id)
        ->and($northLead->fresh()->operational_site_id)->toBe($northSite->id)
        ->and($southLead->fresh()->operator_id)->toBe($southOperator->id)
        ->and($southLead->fresh()->operational_site_id)->toBe($southSite->id)
        // Nobody at $emptySite: the lead keeps the operator it had, is never
        // overwritten with null, and still receives its derived Sede.
        ->and($uncoveredLead->fresh()->operator_id)->toBe($formerOperator->id)
        ->and($uncoveredLead->fresh()->operational_site_id)->toBe($emptySite->id);
});

it('0113 D-3: the Sede comes from the campaign, not from leads.operational_site_id', function () {
    $actor = leadSiteScopeActor();
    $campaignSite = OperationalSite::factory()->withAddress()->create();
    $staleSite = OperationalSite::factory()->withAddress()->create();

    $campaign = leadSiteScopeCampaign($campaignSite);
    $operator = leadSiteScopeOperator($campaign, $campaignSite);
    $staleOperator = leadSiteScopeOperator(leadSiteScopeCampaign($staleSite), $staleSite);

    $lead = Lead::factory()->create([
        'campaign_id' => $campaign->id,
        'operational_site_id' => $staleSite->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($lead->fresh()->operational_site_id)->toBe($campaignSite->id)
        ->and($lead->fresh()->operator_id)->toBe($operator->id)
        ->and($lead->fresh()->operator_id)->not->toBe($staleOperator->id);
});

// ---------------------------------------------------------------------------
// AC-007 (leads half) — no Sede means no candidates, and no Sede write.
// ---------------------------------------------------------------------------

it('0113 AC-007: a lead whose campaign carries no Sede is skipped and its Sede is left untouched', function () {
    $actor = leadSiteScopeActor();
    $staleSite = OperationalSite::factory()->withAddress()->create();
    $formerOperator = User::factory()->create();

    $lead = Lead::factory()->create([
        'campaign_id' => leadSiteScopeCampaign(null)->id,
        'operational_site_id' => $staleSite->id,
        'operator_id' => $formerOperator->id,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 1);

    // An unresolvable Sede is not the same as "clear the one it has": no
    // write happens at all, and there is no fallback pool of all operators.
    expect($lead->fresh()->operational_site_id)->toBe($staleSite->id)
        ->and($lead->fresh()->operator_id)->toBe($formerOperator->id);
});

it('0113 AC-013: every lead uncovered is 200 {0, N}, never the removed 422', function () {
    $actor = leadSiteScopeActor();
    $firstSite = OperationalSite::factory()->withAddress()->create();
    $secondSite = OperationalSite::factory()->withAddress()->create();

    $firstLead = Lead::factory()->create(['campaign_id' => leadSiteScopeCampaign($firstSite)->id]);
    $secondLead = Lead::factory()->create(['campaign_id' => leadSiteScopeCampaign($secondSite)->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$firstLead->id, $secondLead->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 2);

    expect($firstLead->fresh()->operator_id)->toBeNull()
        ->and($secondLead->fresh()->operator_id)->toBeNull()
        ->and($firstLead->fresh()->operational_site_id)->toBe($firstSite->id)
        ->and($secondLead->fresh()->operational_site_id)->toBe($secondSite->id);
});

// ---------------------------------------------------------------------------
// AC-021 — the removed field is rejected, not ignored.
// ---------------------------------------------------------------------------

it('0113 AC-021: operational_site_id in the payload is 422 and nothing is modified', function () {
    $actor = leadSiteScopeActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $campaign = leadSiteScopeCampaign($site);
    $operator = leadSiteScopeOperator($campaign, $site);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect($lead->fresh()->operator_id)->toBeNull()
        ->and($lead->fresh()->operational_site_id)->toBeNull();
});

it('0113 AC-021: mode=balanced rejects operational_site_id too', function () {
    $actor = leadSiteScopeActor();
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create(['campaign_id' => leadSiteScopeCampaign($site)->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');
});
