<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Support\OperationalSiteLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('nonDerivableOpportunityFks')) {
    /**
     * Since the user directive 2026-07-17 makes `product_lines` mandatory to
     * create, a valid one-row collection ships here for every from-lead POST
     * in this file (tests that assert a specific product_lines payload merge
     * the helper FIRST so their own value wins). `company_id`/
     * `company_site_id` were the former mandatory-but-never-derivable fields
     * (amendment rev.1 A-2) — REMOVED entirely per user directive
     * 2026-07-17. Spec 0082 removed the status field entirely (it is
     * computed from the quotes). The supervisor is mandatory only on create.
     *
     * @return array{supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function nonDerivableOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            // User directive 2026-07-23: products_of_interest is mandatory too;
            // the product belongs to the row's OWN category, so this payload
            // never triggers a cross-category product-line addition.
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

if (! function_exists('opportunityFromLeadActor')) {
    /**
     * @param  array<int, string>  $opportunityAbilities
     * @param  array<int, string>  $leadAbilities
     */
    function opportunityFromLeadActor(array $opportunityAbilities, array $leadAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('completeLead')) {
    /**
     * A lead with its own registry AND its own source (spec 0041 D-3), so
     * both locked fields lock — `source_id` derives ONLY from the lead's own
     * source (the campaign no longer carries one, campaign-source fallback
     * removed). The lead's own `operational_site_id` (unrelated to
     * Opportunity, which no longer has that field) still carries a real
     * site, mirroring the common-case fixture.
     *
     * Spec 0094, D-1/D-2: `campaigns.business_function_id`/
     * `product_category_id` no longer exist as columns — CampaignFactory's
     * default (standalone) campaign auto-creates ONE coherent
     * `campaign_product_lines` row of its own (its OWN business function
     * matches its OWN category, satisfying the same
     * CategoryHierarchy::effectiveBusinessFunction() check the write path
     * enforces), so this fixture no longer builds the pair itself.
     * Requirement changed by spec 0094, not test tampering. Shared with
     * OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).
     */
    function completeLead(): Lead
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $campaign = Campaign::factory()->create();

        return Lead::factory()->create([
            'campaign_id' => $campaign->id,
            'registry_id' => $registry->id,
            'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
            'source_id' => $source->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-060 — GET opportunity-defaults, lead completo -> 2 campi valorizzati e
// bloccati (spec 0041 D-3: referent_id is no longer derivable, registry_id
// comes from the lead itself, not the campaign; amendment rev.3:
// business_function_id/product_category_id are NO LONGER locked scalars —
// they surface as `product_lines`, see AC-102/103 below; user directive
// 2026-07-17: operational_site_id is REMOVED from the derivable set)
// ---------------------------------------------------------------------------

it('opportunity-defaults: a complete lead locks both derivable fields (AC-060, AC-050 spec 0041)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.lead_id'))->toBe($lead->id);
    expect($response->json('data.existing_opportunity_id'))->toBeNull();
    expect($response->json('data.values.registry_id'))->toBe($lead->registry_id);
    expect($response->json('data.values.source_id'))->toBe($lead->source_id);
    expect($response->json('data.values'))->not->toHaveKey('business_function_id');
    expect($response->json('data.values'))->not->toHaveKey('product_category_id');
    expect($response->json('data.locked_fields'))->toEqualCanonicalizing([
        'source_id', 'registry_id',
    ]);
    expect($response->json('data.locked_fields'))->not->toContain('referent_id');
    expect($response->json('data.references.registry.id'))->toBe($lead->registry_id);
});

// ---------------------------------------------------------------------------
// User directive 2026-07-23 — the Sede operativa is inherited on conversion.
// It is a PLAIN default: present in `values`/`references`, never in
// `locked_fields` (supersedes the 2026-07-17 "removed from the derivable set"
// directive only in that sense).
// ---------------------------------------------------------------------------

it('opportunity-defaults: exposes the lead operational site as an unlocked default with its composed label', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    $site = $lead->operationalSite()->with('addresses.city')->firstOrFail();

    expect($response->json('data.values.operational_site_id'))->toBe($lead->operational_site_id);
    expect($response->json('data.locked_fields'))->not->toContain('operational_site_id');
    expect($response->json('data.references.operational_site.id'))->toBe($lead->operational_site_id);
    expect($response->json('data.references.operational_site.label'))
        ->toBe(OperationalSiteLabel::compose($site->primaryAddress));
});

it('opportunity-defaults: a lead without a site derives a null operational site', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $lead->update(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.values.operational_site_id'))->toBeNull();
    expect($response->json('data.references.operational_site'))->toBeNull();
});

// AC-102/AC-103 product_lines derivation coverage lives in
// OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).

// ---------------------------------------------------------------------------
// AC-062 — authz + existing_opportunity_id
// ---------------------------------------------------------------------------

it('opportunity-defaults: 403 without opportunities.create', function () {
    $actor = opportunityFromLeadActor([], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertForbidden();
});

it('opportunity-defaults: 403 without leads.view', function () {
    $actor = opportunityFromLeadActor(['create'], []);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertForbidden();
});

it('opportunity-defaults: 404 for a non-existent lead', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/999999/opportunity-defaults')->assertNotFound();
});

it('opportunity-defaults: existing_opportunity_id is populated when the lead is already linked (AC-062)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $opportunity = Opportunity::factory()->create(['lead_id' => $lead->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")
        ->assertOk()
        ->assertJsonPath('data.existing_opportunity_id', $opportunity->id);
});

// ---------------------------------------------------------------------------
// AC-063 — POST with lead_id: derivation, prohibited, unique
// ---------------------------------------------------------------------------

it('create with lead_id: the server writes the derived attributes (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'From lead deal',
        'lead_id' => $lead->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'lead_id' => $lead->id,
        'registry_id' => $lead->registry_id,
        'source_id' => $lead->source_id,
    ]);
});

// AC-102/AC-103 create-with-lead product_lines coverage lives in
// OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).

it('create with lead_id: referent_id is NOT derived/prohibited, freely chosen even with a complete lead (spec 0041 D-3)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $referent = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Freely chosen referent',
        'lead_id' => $lead->id,
        'referent_id' => $referent->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    expect($response->json('data.referent_id'))->toBe($referent->id);
});

it('create with lead_id: sending a derivable field with a non-null derivation -> 422 prohibited (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $otherRegistry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Conflicting registry',
        'lead_id' => $lead->id,
        'registry_id' => $otherRegistry->id,
    ], nonDerivableOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('create with lead_id: a second opportunity for the same lead -> 422 unique (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'First', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();

    $this->postJson('/api/opportunities', array_merge(['name' => 'Second', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('lead_id');

    expect(Opportunity::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-064 — UPDATE lock enforcement
// ---------------------------------------------------------------------------

it('update: a locked field with a DIFFERENT value -> 422 (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Locked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $otherRegistry = Registry::factory()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['registry_id' => $otherRegistry->id])
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');
});

it('update: the SAME value for a locked field -> 200, no-op (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Locked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", ['registry_id' => $lead->registry_id])
        ->assertOk()
        ->assertJsonPath('data.registry_id', $lead->registry_id);
});

it('update: referent_id is freely editable even when the opportunity has a lead (spec 0041 D-3)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Unlocked referent', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $newReferent = Referent::factory()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['referent_id' => $newReferent->id])
        ->assertOk()
        ->assertJsonPath('data.referent_id', $newReferent->id);
});

it('update: lead_id is prohibited (AC-064)', function () {
    $actor = opportunityFromLeadActor(['update'], []);
    $opportunity = Opportunity::factory()->create();
    $otherLead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['lead_id' => $otherLead->id])
        ->assertStatus(422)->assertJsonValidationErrors('lead_id');
});

// operational_site_id was Opportunity's third BR-1-derivable/lockable field
// (both the "NULL derivation stays freely editable" AC-064 case and the
// AC-089 "locked when the lead has one, mandatory when it does not" suite
// exercised it) — REMOVED per user directive 2026-07-17: the field no
// longer exists on Opportunity, so neither scenario applies anymore.

// ---------------------------------------------------------------------------
// AC-065 — detail shape (lead {id,label}, locked_fields) + LeadResource.opportunity
// ---------------------------------------------------------------------------

it('detail: an opportunity from a lead exposes lead {id,label} and locked_fields (AC-065)', function () {
    $actor = opportunityFromLeadActor(['create', 'view'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Detail check', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->getJson("/api/opportunities/{$opportunityId}")
        ->assertOk()
        ->assertJsonPath('data.lead.id', $lead->id)
        ->assertJsonPath('data.lead.label', $lead->registry->name)
        ->assertJsonPath('data.locked_fields', [
            'source_id', 'registry_id',
        ]);
});

it('LeadResource exposes opportunity {id,name}|null (AC-065)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}")->assertOk()->assertJsonPath('data.opportunity', null);

    $created = $this->postJson('/api/opportunities', array_merge(['lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    // Spec 0057, D-5: name is derived (OPP_{id}), never a client input.
    $this->getJson("/api/leads/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('data.opportunity', ['id' => $opportunityId, 'name' => 'OPP_'.$opportunityId]);
});
