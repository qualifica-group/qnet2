<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

// `quotes:resync-workflow-status` — the one-off backfill for the 2026-09-08
// fallback: `quotes.quote_workflow_status_id` is persisted and re-resolved
// only on a write, so the offers created BEFORE the fix keep the global
// default status they were born with even though they now resolve onto the
// workflow their Opportunita's product lines match.
uses(RefreshDatabase::class);

/**
 * A workflow keyed on $category, carrying its own system status rows.
 */
function resyncWorkflowOn(ProductCategory $category): QuoteWorkflow
{
    $workflow = QuoteWorkflow::factory()->create();

    foreach (['open', 'closed_won', 'closed_lost'] as $key) {
        QuoteWorkflowStatus::factory()->system($key)->create(['quote_workflow_id' => $workflow->id]);
    }

    $workflow->criteria()->create(['field' => 'product_category_id', 'value_id' => $category->id]);

    return $workflow;
}

/**
 * A line-less offer whose Opportunita' is classified on $category.
 */
function resyncLineLessQuote(ProductCategory $category): Quote
{
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);

    return Quote::factory()->for($opportunity)->create();
}

function resyncCategory(): ProductCategory
{
    return ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
}

it('moves a line-less offer onto the open row of the workflow its opportunity category matches', function () {
    $category = resyncCategory();
    $quote = resyncLineLessQuote($category);
    $globalStatusId = $quote->quote_workflow_status_id;
    $workflow = resyncWorkflowOn($category);

    $this->artisan('quotes:resync-workflow-status')->assertExitCode(0);

    $resolvedOpen = $workflow->statuses()->where('system_key', 'open')->sole();

    expect($resolvedOpen->id)->not->toBe($globalStatusId)
        ->and($quote->fresh()->quote_workflow_status_id)->toBe($resolvedOpen->id);
});

it('leaves an offer carrying its own revenue lines untouched', function () {
    $category = resyncCategory();
    $quote = resyncLineLessQuote($category);
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['category_id' => resyncCategory()->id])->id,
    ]);
    $originalStatusId = $quote->quote_workflow_status_id;
    resyncWorkflowOn($category);

    $this->artisan('quotes:resync-workflow-status')->assertExitCode(0);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($originalStatusId);
});

it('writes nothing under --dry-run, and is idempotent on a second run', function () {
    $category = resyncCategory();
    $quote = resyncLineLessQuote($category);
    $globalStatusId = $quote->quote_workflow_status_id;
    resyncWorkflowOn($category);

    $this->artisan('quotes:resync-workflow-status', ['--dry-run' => true])->assertExitCode(0);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($globalStatusId);

    $this->artisan('quotes:resync-workflow-status')->assertExitCode(0);
    $afterFirstRun = $quote->fresh()->quote_workflow_status_id;

    $this->artisan('quotes:resync-workflow-status')->assertExitCode(0);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($afterFirstRun)
        ->and($afterFirstRun)->not->toBe($globalStatusId);
});
