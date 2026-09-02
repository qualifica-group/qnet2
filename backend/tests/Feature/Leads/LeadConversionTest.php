<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadConversionActor')) {
    /**
     * @param  array<int, string>  $leadAbilities
     * @param  array<int, string>  $opportunityAbilities
     */
    function leadConversionActor(array $leadAbilities, array $opportunityAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('convertibleLeadFixture')) {
    /**
     * A POST /api/leads payload with `convert_to_opportunity: true` plus
     * every reference needed to make the derivation succeed: a campaign, an
     * operator, and a site. `source_id` is the LEAD's own (the campaign no
     * longer carries a source; the campaign-source fallback was removed).
     *
     * Spec 0094, D-1/D-2: `campaigns.business_function_id`/
     * `product_category_id` no longer exist as columns — CampaignFactory's
     * default (standalone) campaign always carries ONE coherent
     * `campaign_product_lines` row of its own, created in its own
     * `afterCreating()` hook, so `businessFunction`/`productCategory` below
     * are read OFF that persisted row rather than passed into create().
     * Requirement changed by spec 0094, not test tampering.
     *
     * Returns both the payload and the models tests assert against.
     *
     * @return array{payload: array<string, mixed>, registry: Registry, source: Source, businessFunction: BusinessFunction, productCategory: ProductCategory, operator: User, site: OperationalSite}
     */
    function convertibleLeadFixture(): array
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $campaign = Campaign::factory()->create();
        $line = $campaign->productLines()->with(['businessFunction', 'productCategory'])->firstOrFail();
        $operator = User::factory()->create();
        $site = OperationalSite::factory()->create();

        return [
            'payload' => [
                'registry_id' => $registry->id,
                'source_id' => $source->id,
                'campaign_id' => $campaign->id,
                'operator_id' => $operator->id,
                'operational_site_id' => $site->id,
                'convert_to_opportunity' => true,
            ],
            'registry' => $registry,
            'source' => $source,
            'businessFunction' => $line->businessFunction,
            'productCategory' => $line->productCategory,
            'operator' => $operator,
            'site' => $site,
        ];
    }
}

// ---------------------------------------------------------------------------
// A) Contextual conversion — happy path (AC-001..AC-007)
// ---------------------------------------------------------------------------

it('AC-001: creates exactly one Opportunity linked to the new lead', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    expect(Opportunity::where('lead_id', $response->json('data.id'))->count())->toBe(1);
});

it('AC-002: the created Opportunity has the lead.operator as its second Gestore Account, an empty first slot and an empty supervisor', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('managers');

    // Directive 2026-07-22: the Operator becomes the second "Gestore Account"
    // (position 2, G.A. 1 left as a persistent empty slot), not the
    // Supervisor — which stays empty.
    expect($opportunity->supervisor_id)->toBeNull();
    expect($opportunity->managers)->toHaveCount(1);
    expect($opportunity->managers->first()->id)->toBe($fixture['operator']->id);
    expect($opportunity->managers->first()->pivot->position)->toBe(2);
});

it('AC-003: the created Opportunity has registry_id/source_id derived from the lead', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->registry_id)->toBe($fixture['registry']->id);
    expect($opportunity->source_id)->toBe($fixture['source']->id);
});

// User directive 2026-07-23: the converted Opportunity inherits the lead's
// Sede operativa (plain default, never locked).
it('the created Opportunity inherits the lead operational site', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->operational_site_id)->toBe($fixture['site']->id);
});

it('the created Opportunity has a null operational site when the lead has none', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    $payload = $fixture['payload'];
    unset($payload['operational_site_id']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $payload)->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->operational_site_id)->toBeNull();
});

it('AC-004: the created Opportunity has exactly one product line matching the derived pair', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('productLines');

    expect($opportunity->productLines)->toHaveCount(1);
    expect($opportunity->productLines->first()->business_function_id)->toBe($fixture['businessFunction']->id);
    expect($opportunity->productLines->first()->product_category_id)->toBe($fixture['productCategory']->id);
});

// Spec 0057, D-5: the name is no longer composed from the derived product
// category — it is always `OPP_{id}`, regardless of what the conversion derives.
it('AC-005: the created Opportunity name is derived as OPP_{id}', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
});

it('AC-007: the response exposes data.opportunity {id,name} and lead_status converted_to_opportunity', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunityId = $response->json('data.opportunity.id');
    expect($opportunityId)->not->toBeNull();
    // Spec 0057, D-5: the name is derived as OPP_{id}, not composed from the
    // lead's derived product category.
    expect($response->json('data.opportunity.name'))->toBe('OPP_'.$opportunityId);
    $response->assertJsonPath('data.lead_status', 'converted_to_opportunity');
});

// ---------------------------------------------------------------------------
// B) Sede/Operatore optional on conversion + rollback (AC-008..AC-012)
//
// Directive 2026-07-21 relaxed spec 0044 AC-008/009: Sede (operational_site_id)
// and Operatore (operator_id) are NO LONGER required when converting. A Lead
// converts without them — the derived Opportunity inherits a null supervisor.
// ---------------------------------------------------------------------------

it('AC-008: missing operator_id with convert_to_opportunity -> 201, opportunity created with no supervisor and no manager', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    $payload = $fixture['payload'];
    unset($payload['operator_id']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $payload)->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('managers');

    // No Operator on the lead -> no first Gestore Account seeded, empty supervisor.
    expect($opportunity->supervisor_id)->toBeNull();
    expect($opportunity->managers)->toHaveCount(0);
});

it('AC-009: missing operational_site_id with convert_to_opportunity -> 201, opportunity created', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    $payload = $fixture['payload'];
    unset($payload['operational_site_id']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $payload)->assertCreated();

    expect(Opportunity::where('lead_id', $response->json('data.id'))->count())->toBe(1);
    expect(Lead::find($response->json('data.id'))->state_id)->toBeNull();
});

it('AC-010: convert_to_opportunity absent keeps the legacy behavior, no opportunity created', function () {
    $actor = leadConversionActor(['create'], []);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertCreated();

    expect(Lead::count())->toBe(1);
    expect(Opportunity::count())->toBe(0);
    $response->assertJsonPath('data.opportunity', null);
});

it('AC-011: convert_to_opportunity without opportunities.create -> 403, no lead created', function () {
    $actor = leadConversionActor(['create'], []);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', $fixture['payload'])->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('AC-012: a campaign with no product line -> 422, transaction rolled back', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    // Spec 0094: the coherent row CampaignFactory auto-creates for a
    // standalone campaign is dropped here, leaving the campaign with none —
    // the "no business function/product category" case now has this shape.
    // Requirement changed by spec 0094, not test tampering.
    $campaign = Campaign::factory()->create();
    $campaign->productLines()->delete();
    $operator = User::factory()->create();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $leadCountBefore = Lead::count();

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'convert_to_opportunity' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Lead::count())->toBe($leadCountBefore);
    expect(Opportunity::count())->toBe(0);
});
