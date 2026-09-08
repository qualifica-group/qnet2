<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\Opportunities\OpportunityStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (factories, relations), so bind the full TestCase +
// RefreshDatabase explicitly — Pest.php only auto-binds them under Feature/.
uses(TestCase::class, RefreshDatabase::class);

/**
 * Spec 0083, D-2/D-8: the Opportunity carries no status of its own anymore —
 * COMPUTED off its Quotes' `quote_workflow_status`, falling back — when it
 * has no Quote — to the `open` row of the workflow its OWN product category
 * resolves to (user directive 2026-09-08), and to the GLOBAL default set only
 * when no workflow matches it.
 */
function resolver(): OpportunityStatusResolver
{
    return app(OpportunityStatusResolver::class);
}

// ---------------------------------------------------------------------------
// AC-030 — the quotes' own statuses drive the summary
// ---------------------------------------------------------------------------

it('collapses quotes sharing one status into a single entry carrying the count', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare', 'color' => 'slate']);
    Quote::factory()->count(3)->create([
        'opportunity_id' => $opportunity->id,
        'quote_workflow_status_id' => $status->id,
    ]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_QUOTES);
    expect($summary['distinct_count'])->toBe(1);
    expect($summary['entries'])->toBe([[
        'id' => $status->id,
        'name' => 'Da approvare',
        'color' => 'slate',
        'group' => $status->group->value,
        'count' => 3,
    ]]);
});

it('collapses quotes whose statuses share the name across different workflows', function () {
    $opportunity = Opportunity::factory()->create();
    $first = QuoteWorkflowStatus::factory()->create(['name' => 'Da qualificare', 'color' => 'amber', 'sort_order' => 10]);
    $second = QuoteWorkflowStatus::factory()->create(['name' => 'Da qualificare', 'color' => 'slate', 'sort_order' => 20]);

    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $first->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $second->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['distinct_count'])->toBe(1);
    expect($summary['entries'])->toBe([[
        'id' => $first->id,
        'name' => 'Da qualificare',
        'color' => 'amber',
        'group' => $first->group->value,
        'count' => 2,
    ]]);
});

it('returns one entry per distinct quote workflow status, ordered by sort_order (AC-030)', function () {
    $opportunity = Opportunity::factory()->create();
    $inCorso = QuoteWorkflowStatus::factory()->create(['name' => 'In corso', 'sort_order' => 30]);
    $inLavorazione = QuoteWorkflowStatus::factory()->create(['name' => 'In lavorazione', 'sort_order' => 20]);

    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $inCorso->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $inLavorazione->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['distinct_count'])->toBe(2);
    expect(array_column($summary['entries'], 'name'))->toBe(['In lavorazione', 'In corso']);
    expect(array_column($summary['entries'], 'count'))->toBe([1, 1]);
});

it('counts quotes per status independently when the distribution is uneven', function () {
    $opportunity = Opportunity::factory()->create();
    $draft = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare', 'sort_order' => 0]);
    $accepted = QuoteWorkflowStatus::factory()->create(['name' => 'Da firmare', 'sort_order' => 10]);

    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $draft->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $accepted->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect(array_column($summary['entries'], 'count'))->toBe([2, 1]);
});

// ---------------------------------------------------------------------------
// AC-031 — the global default fallback, zero quotes
// ---------------------------------------------------------------------------

it("falls back to the global default set's open row when no workflow matches the quote-less opportunity (AC-031)", function () {
    $opportunity = Opportunity::factory()->create();

    $globalOpen = QuoteWorkflowStatus::query()
        ->whereNull('quote_workflow_id')
        ->where('system_key', 'open')
        ->sole();

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary)->toBe([
        'source' => OpportunityStatusResolver::SOURCE_DEFAULT,
        'distinct_count' => 1,
        'entries' => [[
            'id' => $globalOpen->id,
            'name' => $globalOpen->name,
            'color' => $globalOpen->color,
            'group' => $globalOpen->group->value,
            'count' => 0,
        ]],
    ]);
});

it("prefers the quotes' own statuses over the default fallback as soon as one quote exists", function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_QUOTES);
    expect(array_column($summary['entries'], 'name'))->toBe(['Da approvare']);
});

// ---------------------------------------------------------------------------
// Eager-loaded vs bare model: both paths must agree
// ---------------------------------------------------------------------------

it('produces the same summary from an eager-loaded model as from a bare one', function () {
    $opportunity = Opportunity::factory()->create();
    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    Quote::factory()->count(2)->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);

    $bare = resolver()->resolve(Opportunity::query()->findOrFail($opportunity->id));
    $eager = resolver()->resolve(
        Opportunity::query()->with(OpportunityStatusResolver::EAGER_LOADS)->findOrFail($opportunity->id),
    );

    expect($eager)->toBe($bare);
});

it('resolves an eager-loaded page without lazy loading', function () {
    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    $opportunities = Opportunity::factory()->count(3)->create();
    foreach ($opportunities as $opportunity) {
        Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);
    }

    Opportunity::preventLazyLoading();

    $page = Opportunity::query()->with(OpportunityStatusResolver::EAGER_LOADS)->get();
    $summaries = $page->map(fn (Opportunity $row): array => resolver()->resolve($row))->all();

    Opportunity::preventLazyLoading(false);

    expect($summaries)->toHaveCount(3);
    expect(array_column($summaries, 'distinct_count'))->toBe([1, 1, 1]);
});

// ---------------------------------------------------------------------------
// User directive 2026-09-08 — a quote-less opportunity follows the workflow
// its OWN product category resolves to, not the global default set
// ---------------------------------------------------------------------------

/**
 * A workflow carrying its 3 system status rows, matched on $category.
 */
function workflowOnCategory(ProductCategory $category, string $openName): QuoteWorkflow
{
    $workflow = QuoteWorkflow::factory()->create();

    foreach (['open', 'closed_won', 'closed_lost'] as $key) {
        QuoteWorkflowStatus::factory()->system($key)->create([
            'quote_workflow_id' => $workflow->id,
            'name' => $key === 'open' ? $openName : ucfirst($key),
        ]);
    }

    $workflow->criteria()->create(['field' => 'product_category_id', 'value_id' => $category->id]);

    return $workflow;
}

/**
 * An opportunity with no quote at all, classified on one product line.
 */
function quoteLessOpportunityOn(ProductCategory $category): Opportunity
{
    $opportunity = Opportunity::factory()->create();

    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    return $opportunity;
}

it("falls back to the open row of the workflow the opportunity's own category resolves to", function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $workflow = workflowOnCategory($category, 'Da Richiamare');
    $opportunity = quoteLessOpportunityOn($category);

    $open = QuoteWorkflowStatus::query()
        ->where('quote_workflow_id', $workflow->id)
        ->where('system_key', 'open')
        ->sole();

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary)->toBe([
        'source' => OpportunityStatusResolver::SOURCE_DEFAULT,
        'distinct_count' => 1,
        'entries' => [[
            'id' => $open->id,
            'name' => 'Da Richiamare',
            'color' => $open->color,
            'group' => $open->group->value,
            'count' => 0,
        ]],
    ]);
});

it('keeps the quotes branch untouched when the opportunity has one, whatever its category resolves to', function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    workflowOnCategory($category, 'Da Richiamare');
    $opportunity = quoteLessOpportunityOn($category);

    $status = QuoteWorkflowStatus::factory()->create(['name' => 'Da approvare']);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'quote_workflow_status_id' => $status->id]);

    $summary = resolver()->resolve($opportunity->fresh());

    expect($summary['source'])->toBe(OpportunityStatusResolver::SOURCE_QUOTES)
        ->and(array_column($summary['entries'], 'name'))->toBe(['Da approvare']);
});

it('resolves a quote-less page from EAGER_LOADS alone, without lazy loading', function () {
    $category = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    workflowOnCategory($category, 'Da Richiamare');

    foreach (range(1, 3) as $ignored) {
        quoteLessOpportunityOn($category);
    }

    Opportunity::preventLazyLoading();

    $page = Opportunity::query()->with(OpportunityStatusResolver::EAGER_LOADS)->get();
    $summaries = $page->map(fn (Opportunity $row): array => resolver()->resolve($row))->all();

    Opportunity::preventLazyLoading(false);

    expect(array_column($summaries, 'source'))->toBe(array_fill(0, 3, OpportunityStatusResolver::SOURCE_DEFAULT))
        ->and(collect($summaries)->pluck('entries.0.name')->unique()->all())->toBe(['Da Richiamare']);
});
