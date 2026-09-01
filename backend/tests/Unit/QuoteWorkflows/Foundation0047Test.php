<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Touches the database, so bind the full TestCase + RefreshDatabase
// explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('criterionFieldRegistry')) {
    // QuoteCriterionFieldRegistry stopped being static-only (spec 0047
    // amendment 2026-07-27: it now depends on CustomFieldProvider/
    // CustomFieldEntityRegistry) — resolved through the container like every
    // other consumer.
    function criterionFieldRegistry(): QuoteCriterionFieldRegistry
    {
        return app(QuoteCriterionFieldRegistry::class);
    }
}

// ============ AC-005: global default set seeded by the migration ============

// Requirement changed (user directive 2026-08-07): 'validated' is no longer a
// system key — the seeded "Validato" row survives as an ordinary row of the
// `validated` GROUP, unmarked by the 2026_08_07_120000 migration.
it('seeds 4 global default rows (workflow_id null), only open/closed_won/closed_lost carrying a system_key (AC-005)', function () {
    $globalRows = DB::table('quote_workflow_statuses')
        ->whereNull('quote_workflow_id')
        ->orderBy('sort_order')
        ->get();

    expect($globalRows)->toHaveCount(4)
        ->and($globalRows->pluck('system_key')->all())->toBe(['open', null, 'closed_won', 'closed_lost'])
        ->and($globalRows->pluck('group')->all())->toBe(['open', 'validated', 'closed_won', 'closed_lost']);
});

// ============ Models: relations + isSystem() ============

it('creates a workflow and exposes working criteria()/statuses() relations', function () {
    $workflow = QuoteWorkflow::factory()->create(['name' => 'Regione Nord']);

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
    $custom = QuoteWorkflowStatus::factory()->create(['system_key' => null]);
    $system = QuoteWorkflowStatus::factory()->system('open')->create();

    expect($custom->isSystem())->toBeFalse()
        ->and($system->isSystem())->toBeTrue();
});

// ============ AC-022 (partial): QuoteCriterionFieldRegistry allow-list ============

it('allowedFields() returns the 3 native fields (source native) with correct for_select_resource (AC-022/AC-027)', function () {
    $fields = criterionFieldRegistry()->allowedFields();

    expect($fields)->toHaveCount(3);

    $byField = collect($fields)->keyBy('field');

    expect($byField['source_id']['for_select_resource'])->toBe('sources')
        ->and($byField['source_id']['multi_valued'])->toBeFalse()
        ->and($byField['business_function_id']['for_select_resource'])->toBe('business-functions')
        ->and($byField['business_function_id']['multi_valued'])->toBeTrue()
        ->and($byField['product_category_id']['for_select_resource'])->toBe('product-categories')
        ->and($byField['product_category_id']['multi_valued'])->toBeTrue();

    foreach ($fields as $field) {
        expect($field['source'])->toBe('native')
            ->and($field['label'])->toBe("quoteWorkflows.criterionFields.{$field['field']}");
    }
});

it('isAllowed()/existsTable() are consistent with the allow-list', function () {
    $registry = criterionFieldRegistry();

    expect($registry->isAllowed('source_id'))->toBeTrue()
        ->and($registry->isAllowed('not_a_field'))->toBeFalse()
        ->and($registry->existsTable('source_id'))->toBe('sources')
        ->and($registry->existsTable('business_function_id'))->toBe('business_functions')
        ->and($registry->existsTable('product_category_id'))->toBe('product_categories');
});

it('existsTable() rejects a field outside the allow-list', function () {
    criterionFieldRegistry()->existsTable('not_a_field');
})->throws(InvalidArgumentException::class);

// ============ quoteValues() — D-7: inherited vs. offer-line-derived ============

it('quoteValues() reads the INHERITED value from the parent opportunity for source_id, empty when null (D-7)', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->load('opportunity');

    $bare = Quote::factory()->create(['opportunity_id' => Opportunity::factory()->create(['source_id' => null])->id]);
    $bare->load('opportunity');

    expect(criterionFieldRegistry()->quoteValues($quote, 'source_id'))->toBe([$source->id])
        ->and(criterionFieldRegistry()->quoteValues($bare, 'source_id'))->toBe([]);
});

it('quoteValues() extracts distinct values from the OFFER lines for business_function_id/product_category_id (AC-013 groundwork, D-7)', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    // Two categories share functionA: quoteValues() must dedupe the
    // business_function_id even though the categories stay distinct.
    $categoryA1 = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryA2 = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);

    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryA1->id])]);
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryA2->id])]);
    QuoteLine::factory()->for($quote)->create(['product_id' => Product::factory()->create(['category_id' => $categoryB->id])]);

    $quote->load('offerLines.product.category');

    $businessFunctionValues = criterionFieldRegistry()->quoteValues($quote, 'business_function_id');
    $productCategoryValues = criterionFieldRegistry()->quoteValues($quote, 'product_category_id');

    expect($businessFunctionValues)->toEqualCanonicalizing([$functionA->id, $functionB->id])
        ->and($productCategoryValues)->toEqualCanonicalizing([$categoryA1->id, $categoryA2->id, $categoryB->id]);
});

it('quoteValues() returns an empty array when the quote has no offer lines', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quote->load('offerLines.product.category');

    expect(criterionFieldRegistry()->quoteValues($quote, 'business_function_id'))->toBe([]);
});
