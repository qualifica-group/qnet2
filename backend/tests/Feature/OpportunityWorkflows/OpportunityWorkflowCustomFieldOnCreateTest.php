<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\OpportunityStatus;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0047 amendment 2026-07-27, AC-033: on CREATE, custom_fields is
// persisted by App\Models\Concerns\HasCustomFields' `saved` observer on the
// VERY FIRST Opportunity::create() call inside OpportunityService::create()
// (see App\CustomFields\CustomFieldRequestBag's single-primary-entity
// docblock) — strictly BEFORE resolveWorkflowStatus() runs later in that
// same method, so the resolver already reads the persisted value. This test
// goes through the real HTTP pipeline (CaptureCustomFields middleware) to
// verify that ordering for real, not assume it.
uses(RefreshDatabase::class);

if (! function_exists('opportunityCreateActor')) {
    function opportunityCreateActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('opportunities.create');

        return $user;
    }
}

if (! function_exists('mandatoryOpportunityCreatePayload')) {
    /**
     * @return array<string, mixed>
     */
    function mandatoryOpportunityCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'opportunity_status_id' => OpportunityStatus::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): OpportunityWorkflow
    {
        $workflow = OpportunityWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            OpportunityWorkflowStatus::factory()->system($key)->create(['opportunity_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

it('create: resolves the workflow matching a custom relation criterion submitted via custom_fields (AC-033)', function () {
    $definition = CustomFieldDefinition::factory()->forEntity('opportunities')->create([
        'key' => 'preferred_company',
        'type' => 'relation',
        'label' => 'Azienda preferita',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ]);
    $company = Company::factory()->create();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    Sanctum::actingAs(opportunityCreateActor());

    $response = $this->postJson('/api/opportunities', array_merge(
        mandatoryOpportunityCreatePayload(),
        ['custom_fields' => ['preferred_company' => $company->id]],
    ))->assertCreated();

    $opportunityId = $response->json('data.id');
    $expectedOpenStatusId = $workflow->statuses()->where('system_key', 'open')->sole()->id;

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunityId,
        'opportunity_workflow_status_id' => $expectedOpenStatusId,
    ]);

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'opportunities',
        'entity_id' => $opportunityId,
    ]);
});
