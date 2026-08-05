<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\City;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('workflowOpportunityUserWith')) {
    /**
     * @param  array<int, string>  $opportunityAbilities
     * @param  array<int, string>  $leadAbilities
     */
    function workflowOpportunityUserWith(array $opportunityAbilities, array $leadAbilities = []): User
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

if (! function_exists('baseOpportunityWorkflowFks')) {
    /**
     * The mandatory create payload beyond `name` (mirrors OpportunityCrudTest's
     * mandatoryOpportunityFks, redeclared here to keep this file self-contained
     * and independent of that file's load order).
     *
     * @return array{registry_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function baseOpportunityWorkflowFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
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

if (! function_exists('siteWithRegion')) {
    function siteWithRegion(State $state): OperationalSite
    {
        $city = City::factory()->forState($state)->create();

        return OperationalSite::factory()->withAddress($city)->create();
    }
}

// ---------------------------------------------------------------------------
// AC-002 — conversion inherits state_id from the lead
// ---------------------------------------------------------------------------

it('conversion inherits state_id from the lead onto the created opportunity (AC-002)', function () {
    $actor = workflowOpportunityUserWith(['create', 'view'], ['create']);
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    $campaign = Campaign::factory()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);
    $operator = User::factory()->create();
    $state = State::factory()->create();
    $site = siteWithRegion($state);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'convert_to_opportunity' => true,
    ])->assertCreated();

    expect($response->json('data.state_id'))->toBe($state->id);

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->sole();
    expect($opportunity->state_id)->toBe($state->id);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.state_id', $state->id)
        ->assertJsonPath('data.state', ['id' => $state->id, 'name' => $state->name]);
});

// ---------------------------------------------------------------------------
// AC-015 / AC-003 — create with no matching workflow -> global 'open'
// ---------------------------------------------------------------------------

it('create: with no matching workflow, opportunity_workflow_status_id resolves to the global open row (AC-015)', function () {
    $actor = workflowOpportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(
        ['name' => 'Global set deal'],
        baseOpportunityWorkflowFks(),
    ))->assertCreated();

    $globalOpen = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'open')
        ->sole();

    expect($response->json('data.opportunity_workflow_status_id'))->toBe($globalOpen->id)
        ->and($response->json('data.workflow_status.system_key'))->toBe('open')
        ->and($response->json('data.workflow_status.name'))->toBe($globalOpen->name);

    // AC-003: the resolved set is exposed too (the global set's 4 system rows).
    $statusKeys = collect($response->json('data.workflow_statuses'))->pluck('system_key')->all();
    expect($statusKeys)->toEqualCanonicalizing(['open', 'validated', 'closed_won', 'closed_lost']);

    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'opportunity_workflow_status_id' => $globalOpen->id,
    ]);
});

// ---------------------------------------------------------------------------
// AC-017 — opportunity_workflow_status_id outside the resolved set -> 422
// ---------------------------------------------------------------------------

it('create: opportunity_workflow_status_id outside the resolved (global) set -> 422 (AC-017)', function () {
    $actor = workflowOpportunityUserWith(['create']);
    $source = Source::factory()->create();

    $foreignWorkflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $foreignWorkflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $foreignStatus = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => $foreignWorkflow->id,
        'system_key' => null,
    ]);
    Sanctum::actingAs($actor);

    // No source_id submitted -> resolves to the GLOBAL set, not $foreignWorkflow's.
    $this->postJson('/api/opportunities', array_merge(
        ['name' => 'Out of set status', 'opportunity_workflow_status_id' => $foreignStatus->id],
        baseOpportunityWorkflowFks(),
    ))->assertStatus(422)->assertJsonValidationErrors('opportunity_workflow_status_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: an explicit opportunity_workflow_status_id belonging to the resolved workflow is accepted (AC-017)', function () {
    $actor = workflowOpportunityUserWith(['create']);
    $source = Source::factory()->create();

    $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $customStatus = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => $workflow->id,
        'system_key' => null,
        'name' => 'In lavorazione',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(
        ['name' => 'In set status', 'source_id' => $source->id, 'opportunity_workflow_status_id' => $customStatus->id],
        baseOpportunityWorkflowFks(),
    ))->assertCreated();

    expect($response->json('data.opportunity_workflow_status_id'))->toBe($customStatus->id)
        ->and($response->json('data.workflow_status.name'))->toBe('In lavorazione');
});

// ---------------------------------------------------------------------------
// AC-016 — update: resolved set changes, current status remapped by system_key
// ---------------------------------------------------------------------------

it('update: changing source_id to match a new workflow remaps the closed status by system_key (AC-016)', function () {
    $actor = workflowOpportunityUserWith(['create', 'update']);
    $source = Source::factory()->create();

    $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $workflowClosed = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => $workflow->id,
        'system_key' => 'closed_won',
        'name' => 'Chiusa personalizzata',
    ]);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(
        ['name' => 'Remap deal'],
        baseOpportunityWorkflowFks(),
    ))->assertCreated();
    $opportunityId = $created->json('data.id');

    $globalClosed = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'closed_won')
        ->sole();

    // Force the current status to the GLOBAL closed_won row before the update.
    Opportunity::whereKey($opportunityId)->update(['opportunity_workflow_status_id' => $globalClosed->id]);

    $response = $this->patchJson("/api/opportunities/{$opportunityId}", ['source_id' => $source->id])
        ->assertOk();

    expect($response->json('data.opportunity_workflow_status_id'))->toBe($workflowClosed->id)
        ->and($response->json('data.workflow_status.system_key'))->toBe('closed_won');
});
