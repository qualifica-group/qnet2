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
use App\Models\State;
use App\Services\Quotes\QuoteWorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (workflows/criteria/statuses + Opportunity/Quote), so
// bind the full TestCase + RefreshDatabase explicitly (Unit suite has no
// default RefreshDatabase binding), mirroring Foundation0047Test. Spec 0083
// (D-1/D-3/D-7) re-targets the whole resolver at the Quote: `source_id`/
// `state_id` are inherited from `quote.opportunity`, `business_function_id`/
// `product_category_id` come from the quote's own REVENUE offer lines.
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * A workflow carrying its own pinned open/closed_won/closed_lost system
     * rows (AC-004, normally created by the Lane A configurator service — not
     * built by this lane — so tests build them directly via the factory,
     * mirroring the migration's regenerated system-row shape).
     *
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): QuoteWorkflow
    {
        $workflow = QuoteWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            QuoteWorkflowStatus::factory()
                ->system($key)
                ->create(['quote_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

if (! function_exists('workflowResolver')) {
    function workflowResolver(): QuoteWorkflowResolver
    {
        return app(QuoteWorkflowResolver::class);
    }
}

if (! function_exists('quoteForNewOpportunity')) {
    /**
     * A Quote for a fresh Opportunity carrying $opportunityAttributes — the
     * inherited criteria (state_id/source_id/custom.*, D-7) resolve through
     * this parent, never a column on the Quote itself.
     *
     * @param  array<string, mixed>  $opportunityAttributes
     */
    function quoteForNewOpportunity(array $opportunityAttributes = []): Quote
    {
        $opportunity = Opportunity::factory()->create($opportunityAttributes);

        return Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    }
}

if (! function_exists('revenueLineFor')) {
    function revenueLineFor(Quote $quote, Product $product): QuoteLine
    {
        return QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);
    }
}

// ---------------------------------------------------------------------------
// resolve() — AC-010/AC-011/AC-012/AC-013/AC-014
// ---------------------------------------------------------------------------

it('resolves to the global default set when no active workflow matches (AC-010)', function () {
    $quote = quoteForNewOpportunity(['source_id' => null, 'state_id' => null]);

    $workflow = workflowResolver()->resolve($quote);

    expect($workflow)->toBeNull();

    $target = workflowResolver()->targetStatus($quote, $workflow);

    expect($target->quote_workflow_id)->toBeNull()
        ->and($target->system_key)->toBe('open');
});

it('picks the more specific of two matching workflows: 2 criteria beats 1 (AC-011)', function () {
    $source = Source::factory()->create();
    $state = State::factory()->create();

    $quote = quoteForNewOpportunity(['source_id' => $source->id, 'state_id' => $state->id]);

    $lessSpecific = workflowWithSystemStatuses();
    $lessSpecific->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $moreSpecific = workflowWithSystemStatuses();
    $moreSpecific->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $moreSpecific->criteria()->create(['field' => 'state_id', 'value_id' => $state->id]);

    $resolved = workflowResolver()->resolve($quote);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($moreSpecific->id);
});

it('tie-breaks equal specificity by id asc (AC-012)', function () {
    $source = Source::factory()->create();
    $quote = quoteForNewOpportunity(['source_id' => $source->id]);

    $first = workflowWithSystemStatuses();
    $first->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $second = workflowWithSystemStatuses();
    $second->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $resolved = workflowResolver()->resolve($quote);

    expect($resolved->id)->toBe(min($first->id, $second->id));
});

it('matches business_function_id/product_category_id against ANY offer line row, AND across criteria (AC-013)', function () {
    $functionA = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $functionB = BusinessFunction::factory()->create();
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);

    $productA = Product::factory()->create(['category_id' => $categoryA->id]);
    $productB = Product::factory()->create(['category_id' => $categoryB->id]);

    $quote = quoteForNewOpportunity();
    revenueLineFor($quote, $productA);
    revenueLineFor($quote, $productB);

    $matching = workflowWithSystemStatuses();
    $matching->criteria()->create(['field' => 'business_function_id', 'value_id' => $functionA->id]);
    $matching->criteria()->create(['field' => 'product_category_id', 'value_id' => $categoryB->id]);

    expect(workflowResolver()->resolve($quote)->id)->toBe($matching->id);

    // A criterion whose value is not present on ANY offer line row never
    // matches, even though the OTHER criterion (product_category_id) would.
    $otherFunction = BusinessFunction::factory()->create();
    $nonMatching = workflowWithSystemStatuses();
    $nonMatching->criteria()->create(['field' => 'business_function_id', 'value_id' => $otherFunction->id]);
    $nonMatching->criteria()->create(['field' => 'product_category_id', 'value_id' => $categoryB->id]);

    expect(workflowResolver()->resolve($quote)->id)->toBe($matching->id);
});

it('ignores an inactive workflow (AC-014)', function () {
    $source = Source::factory()->create();
    $quote = quoteForNewOpportunity(['source_id' => $source->id]);

    $inactive = workflowWithSystemStatuses(['is_active' => false]);
    $inactive->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    expect(workflowResolver()->resolve($quote))->toBeNull();
});

// ---------------------------------------------------------------------------
// targetStatus() — D3
// ---------------------------------------------------------------------------

it('targetStatus keeps the current status when it already belongs to the resolved set', function () {
    $workflow = workflowWithSystemStatuses();
    $custom = QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $workflow->id,
        'system_key' => null,
        'sort_order' => 5,
    ]);

    $quote = Quote::factory()->create(['quote_workflow_status_id' => $custom->id]);

    $target = workflowResolver()->targetStatus($quote, $workflow);

    expect($target->id)->toBe($custom->id);
});

it('re-maps by system_key when the resolved set changes and the current status does not belong to it (AC-016)', function () {
    $oldWorkflow = workflowWithSystemStatuses();
    $newWorkflow = workflowWithSystemStatuses();

    $oldClosed = $oldWorkflow->statuses()->where('system_key', 'closed_won')->sole();
    $quote = Quote::factory()->create(['quote_workflow_status_id' => $oldClosed->id]);

    $target = workflowResolver()->targetStatus($quote, $newWorkflow);

    $newClosed = $newWorkflow->statuses()->where('system_key', 'closed_won')->sole();
    expect($target->id)->toBe($newClosed->id);
});

it('falls back to the open row when the current status is custom and does not belong to the new set', function () {
    $oldWorkflow = workflowWithSystemStatuses();
    $customOld = QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $oldWorkflow->id,
        'system_key' => null,
    ]);
    $newWorkflow = workflowWithSystemStatuses();

    $quote = Quote::factory()->create(['quote_workflow_status_id' => $customOld->id]);

    $target = workflowResolver()->targetStatus($quote, $newWorkflow);

    $newOpen = $newWorkflow->statuses()->where('system_key', 'open')->sole();
    expect($target->id)->toBe($newOpen->id);
});

it('falls back to the set open row when the quote has no current status', function () {
    $workflow = workflowWithSystemStatuses();

    // `quotes.quote_workflow_status_id` is NOT NULL (spec 0083) — a transient,
    // never-persisted Quote is the only way to exercise this defensive
    // branch, mirroring ValidatesQuoteWorkflowStatus::resolutionQuote().
    $quote = new Quote;
    $quote->quote_workflow_status_id = null;

    $target = workflowResolver()->targetStatus($quote, $workflow);
    $open = $workflow->statuses()->where('system_key', 'open')->sole();

    expect($target->id)->toBe($open->id);
});

// ---------------------------------------------------------------------------
// resolveAndAssign()
// ---------------------------------------------------------------------------

it('resolveAndAssign() persists only quote_workflow_status_id, to the global open row absent any match', function () {
    $quote = quoteForNewOpportunity();

    workflowResolver()->resolveAndAssign($quote);

    $globalOpen = QuoteWorkflowStatus::query()
        ->whereNull('quote_workflow_id')
        ->where('system_key', 'open')
        ->sole();

    expect($quote->quote_workflow_status_id)->toBe($globalOpen->id);
    $this->assertDatabaseHas('quotes', [
        'id' => $quote->id,
        'quote_workflow_status_id' => $globalOpen->id,
    ]);
});
