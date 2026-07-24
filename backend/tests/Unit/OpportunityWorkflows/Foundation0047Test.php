<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
use App\Models\Source;
use App\Support\OpportunityWorkflows\CriterionFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Touches the database, so bind the full TestCase + RefreshDatabase
// explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

// ============ AC-005: global default set seeded by the migration ============

it('seeds exactly 4 global default rows (workflow_id null) with system_key open/validated/closed_won/closed_lost (AC-005)', function () {
    $globalRows = DB::table('opportunity_workflow_statuses')
        ->whereNull('opportunity_workflow_id')
        ->orderBy('sort_order')
        ->get();

    expect($globalRows)->toHaveCount(4)
        ->and($globalRows->pluck('system_key')->all())->toBe(['open', 'validated', 'closed_won', 'closed_lost'])
        ->and($globalRows->pluck('group')->all())->toBe(['open', 'validated', 'closed_won', 'closed_lost']);
});

// ============ Models: relations + isSystem() ============

it('creates a workflow and exposes working criteria()/statuses() relations', function () {
    $workflow = OpportunityWorkflow::factory()->create(['name' => 'Regione Nord']);

    $source = Source::factory()->create();
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $workflow->statuses()->create(['name' => 'In lavorazione', 'sort_order' => 5]);

    expect($workflow->criteria()->count())->toBe(1)
        ->and($workflow->criteria->first()->field)->toBe('source_id')
        ->and($workflow->criteria->first()->workflow->is($workflow))->toBeTrue()
        ->and($workflow->statuses()->count())->toBe(1)
        ->and($workflow->statuses->first()->name)->toBe('In lavorazione')
        ->and($workflow->statuses->first()->workflow->is($workflow))->toBeTrue();
});

it('isSystem() is true only for a row carrying a system_key', function () {
    $custom = OpportunityWorkflowStatus::factory()->create(['system_key' => null]);
    $system = OpportunityWorkflowStatus::factory()->system('open')->create();

    expect($custom->isSystem())->toBeFalse()
        ->and($system->isSystem())->toBeTrue();
});

// ============ AC-022 (partial): CriterionFieldRegistry allow-list ============

it('allowedFields() returns the 4 allow-listed fields with correct for_select_resource (AC-022)', function () {
    $fields = CriterionFieldRegistry::allowedFields();

    expect($fields)->toHaveCount(4);

    $byField = collect($fields)->keyBy('field');

    expect($byField['state_id']['for_select_resource'])->toBe('states')
        ->and($byField['state_id']['multi_valued'])->toBeFalse()
        ->and($byField['source_id']['for_select_resource'])->toBe('sources')
        ->and($byField['source_id']['multi_valued'])->toBeFalse()
        ->and($byField['business_function_id']['for_select_resource'])->toBe('business-functions')
        ->and($byField['business_function_id']['multi_valued'])->toBeTrue()
        ->and($byField['product_category_id']['for_select_resource'])->toBe('product-categories')
        ->and($byField['product_category_id']['multi_valued'])->toBeTrue();

    foreach ($fields as $field) {
        expect($field['label'])->toBe("opportunityWorkflows.criterionFields.{$field['field']}");
    }
});

it('isAllowed()/existsTable() are consistent with the allow-list', function () {
    expect(CriterionFieldRegistry::isAllowed('state_id'))->toBeTrue()
        ->and(CriterionFieldRegistry::isAllowed('not_a_field'))->toBeFalse()
        ->and(CriterionFieldRegistry::existsTable('state_id'))->toBe('states')
        ->and(CriterionFieldRegistry::existsTable('source_id'))->toBe('sources')
        ->and(CriterionFieldRegistry::existsTable('business_function_id'))->toBe('business_functions')
        ->and(CriterionFieldRegistry::existsTable('product_category_id'))->toBe('product_categories');
});

it('existsTable() rejects a field outside the allow-list', function () {
    CriterionFieldRegistry::existsTable('not_a_field');
})->throws(InvalidArgumentException::class);

it('opportunityValues() reads a direct column for state_id/source_id, empty when null', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $source->id, 'state_id' => null]);

    expect(CriterionFieldRegistry::opportunityValues($opportunity, 'source_id'))->toBe([$source->id])
        ->and(CriterionFieldRegistry::opportunityValues($opportunity, 'state_id'))->toBe([]);
});

it('opportunityValues() extracts distinct values from productLines for business_function_id/product_category_id (AC-013 groundwork)', function () {
    $opportunity = Opportunity::factory()->create();

    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $opportunity->productLines()->create(['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id]);
    $opportunity->productLines()->create(['business_function_id' => $functionB->id, 'product_category_id' => $categoryA->id]);
    // Same business_function_id repeated with a DIFFERENT category (the pair
    // stays unique): opportunityValues() must dedupe the business_function_id.
    $opportunity->productLines()->create(['business_function_id' => $functionA->id, 'product_category_id' => $categoryB->id]);

    $opportunity->load('productLines');

    $businessFunctionValues = CriterionFieldRegistry::opportunityValues($opportunity, 'business_function_id');
    $productCategoryValues = CriterionFieldRegistry::opportunityValues($opportunity, 'product_category_id');

    expect($businessFunctionValues)->toEqualCanonicalizing([$functionA->id, $functionB->id])
        ->and($productCategoryValues)->toEqualCanonicalizing([$categoryA->id, $categoryB->id]);
});

it('opportunityValues() returns an empty array when the opportunity has no productLines rows', function () {
    $opportunity = Opportunity::factory()->create();
    $opportunity->load('productLines');

    expect(CriterionFieldRegistry::opportunityValues($opportunity, 'business_function_id'))->toBe([]);
});
