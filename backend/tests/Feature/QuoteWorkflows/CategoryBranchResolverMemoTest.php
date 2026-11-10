<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Support\QuoteWorkflows\CategoryBranchResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression (2026-11-07): the per-Quote memo was keyed by spl_object_id, which
 * PHP hands to a new object once the old one is freed. In a long process (a
 * seeder, an import job) a fresh Quote inherited the branch of a freed one and
 * resolved the wrong workflow.
 */
uses(RefreshDatabase::class);

if (! function_exists('branchMemoQuoteOn')) {
    function branchMemoQuoteOn(ProductCategory $category): Quote
    {
        $opportunity = new Opportunity;
        $opportunity->setRelation('productLines', new Collection([
            (new OpportunityProductLine)->forceFill(['product_category_id' => $category->id]),
        ]));

        $quote = new Quote;
        $quote->setRelation('opportunity', $opportunity);
        $quote->setRelation('offerLines', new Collection);

        return $quote;
    }
}

it('never serves a freed quote branch to a new quote that reuses its object id', function () {
    $resolver = app(CategoryBranchResolver::class);
    $first = ProductCategory::factory()->create();
    $second = ProductCategory::factory()->create();

    $old = branchMemoQuoteOn($first);
    $oldObjectId = spl_object_id($old);
    expect($resolver->distancesFor($old))->toHaveKey($first->id);
    unset($old);

    // Allocate until PHP reuses the freed id (bounded: the slot is reused at once in practice).
    $held = [];
    $new = null;

    for ($attempt = 0; $attempt < 1000 && $new === null; $attempt++) {
        $candidate = new Quote;

        if (spl_object_id($candidate) === $oldObjectId) {
            $new = $candidate;
        } else {
            $held[] = $candidate;
        }
    }

    expect($new)->not->toBeNull();

    $opportunity = new Opportunity;
    $opportunity->setRelation('productLines', new Collection([
        (new OpportunityProductLine)->forceFill(['product_category_id' => $second->id]),
    ]));
    $new->setRelation('opportunity', $opportunity);
    $new->setRelation('offerLines', new Collection);

    expect($resolver->distancesFor($new))->toHaveKey($second->id)->not->toHaveKey($first->id);
});
