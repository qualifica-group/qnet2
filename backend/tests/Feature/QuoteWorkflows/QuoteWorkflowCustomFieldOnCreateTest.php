<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0047 amendment 2026-07-27, spec 0083 D-7: a custom relation criterion
// still reads from the `opportunities` entity_type's custom_fields (the
// definitions stay anchored there), but the resolving RECORD is the Quote
// now — its inherited value comes from `quote.opportunity.custom_fields`,
// never a field on the Quote itself. This test goes through the real
// POST /api/quotes HTTP pipeline (QuoteWorkflowResolver, inside
// QuoteService::create()) to verify that end-to-end, not assume it.
uses(RefreshDatabase::class);

if (! function_exists('quoteCreateActor')) {
    function quoteCreateActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('quotes.create');

        return $user;
    }
}

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): QuoteWorkflow
    {
        $workflow = QuoteWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            QuoteWorkflowStatus::factory()->system($key)->create(['quote_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

it('create: resolves the workflow matching a custom relation criterion inherited from the parent Opportunity (AC-033, spec 0083 D-7)', function () {
    $definition = CustomFieldDefinition::factory()->forEntity('opportunities')->create([
        'key' => 'preferred_company',
        'type' => 'relation',
        'label' => 'Azienda preferita',
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one', 'for_select_resource' => 'companies'],
    ]);
    $company = Company::factory()->create();

    $workflow = workflowWithSystemStatuses();
    $workflow->criteria()->create(['field' => "custom.{$definition->key}", 'value_id' => $company->id]);

    $opportunity = Opportunity::factory()->create();
    CustomFieldValue::factory()->forEntity('opportunities', $opportunity->id)->create(['values' => ['preferred_company' => $company->id]]);

    // Spec 0102: POST /api/quotes now requires at least one offer_lines row.
    // A category with an EFFECTIVE business function so the auto-add
    // coverage path never trips the 422 guard (OpportunityProductLineCoverage).
    $productCategory = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $product = Product::factory()->create(['category_id' => $productCategory->id]);

    Sanctum::actingAs(quoteCreateActor());

    $response = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10],
        ],
    ])->assertCreated();

    $expectedOpenStatusId = $workflow->statuses()->where('system_key', 'open')->sole()->id;

    $this->assertDatabaseHas('quotes', [
        'id' => $response->json('data.id'),
        'quote_workflow_status_id' => $expectedOpenStatusId,
    ]);
});
