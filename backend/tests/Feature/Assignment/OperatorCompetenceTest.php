<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\CampaignProductLine;
use App\Models\EmploymentProfile;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\ImportRowCompetence;
use App\Services\Assignment\LeadCompetence;
use App\Services\Assignment\OperatorCompetence;
use App\Services\Assignment\QuoteCompetence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Spec 0110 — the competence rule (INV-2/3/4) and the three requirement
 * resolvers (INV-1). AC-010..AC-015.
 */
if (! function_exists('competentUser')) {
    /**
     * A user whose employment profile carries the given function and
     * categories. A null function, or no category at all, is the wildcard
     * state of INV-4b.
     */
    function competentUser(?BusinessFunction $function, ProductCategory ...$categories): User
    {
        $user = User::factory()->create();

        EmploymentProfile::factory()
            ->for($user)
            ->competentIn(...$categories)
            ->create(['business_function_id' => $function?->id]);

        return $user;
    }
}

/** A category owning its own business function (spec 0023: the chain's only one). */
if (! function_exists('categoryWithFunction')) {
    function categoryWithFunction(BusinessFunction $function, ?ProductCategory $parent = null): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => $function->id,
            'parent_id' => $parent?->id,
        ]);
    }
}

/**
 * A standalone campaign classified with exactly $category. CampaignFactory
 * creates a product line of its own (spec 0094 D-1), so it is dropped first:
 * these tests assert on an EXACT requirement set.
 */
if (! function_exists('campaignClassifiedAs')) {
    function campaignClassifiedAs(ProductCategory $category): Campaign
    {
        $campaign = Campaign::factory()->create();
        $campaign->productLines()->delete();

        CampaignProductLine::factory()->create([
            'campaign_id' => $campaign->id,
            'product_category_id' => $category->id,
        ]);

        return $campaign;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — both halves are required (INV-3).
// ---------------------------------------------------------------------------

it('0110 AC-010: competence requires the matching function AND the matching category', function () {
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);
    $otherCategory = categoryWithFunction($function);

    $matching = competentUser($function, $category);
    $wrongFunction = competentUser($otherFunction, $category);
    $wrongCategory = competentUser($function, $otherCategory);

    $candidates = [$matching->id, $wrongFunction->id, $wrongCategory->id];

    expect(app(OperatorCompetence::class)->competent($candidates, [$category->id]))
        ->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-011 — a configured parent covers its descendants (INV-2).
// ---------------------------------------------------------------------------

it('0110 AC-011: a user competent on the parent category covers its descendants', function () {
    $function = BusinessFunction::factory()->create();
    $parent = categoryWithFunction($function);
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);

    $user = competentUser($function, $parent);

    // The descendants inherit the parent's function (spec 0023), so both
    // halves line up down the whole branch.
    expect(app(OperatorCompetence::class)->competent([$user->id], [$grandchild->id]))
        ->toBe([$user->id]);
});

// ---------------------------------------------------------------------------
// AC-012 / AC-013 — the two deroghe (INV-4).
// ---------------------------------------------------------------------------

it('0110 AC-012: a record requiring no category leaves every candidate in place', function () {
    $function = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);
    $configured = competentUser($function, $category);
    $plain = User::factory()->create();

    expect(app(OperatorCompetence::class)->competent([$configured->id, $plain->id], []))
        ->toBe([$configured->id, $plain->id]);
});

it('0110 AC-013: a user missing either half of the competence is a wildcard', function () {
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);
    $otherCategory = categoryWithFunction($function);

    $noCategories = competentUser($otherFunction);
    $noFunction = competentUser(null, $otherCategory);
    $noProfileAtAll = User::factory()->create();
    $configuredButWrong = competentUser($otherFunction, $otherCategory);

    $candidates = [$noCategories->id, $noFunction->id, $noProfileAtAll->id, $configuredButWrong->id];

    expect(app(OperatorCompetence::class)->competent($candidates, [$category->id]))
        ->toBe([$noCategories->id, $noFunction->id, $noProfileAtAll->id]);
});

// ---------------------------------------------------------------------------
// AC-014 — the staged import row's requirement (INV-1).
// ---------------------------------------------------------------------------

it('0110 AC-014: a row with its own products requires their categories and ignores the campaign', function () {
    $function = BusinessFunction::factory()->create();
    $productCategory = categoryWithFunction($function);
    $campaignCategory = categoryWithFunction($function);

    $product = Product::factory()->create(['category_id' => $productCategory->id]);
    $campaign = campaignClassifiedAs($campaignCategory);

    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => [$product->id]]);

    $required = app(ImportRowCompetence::class)->requiredByRow(
        collect([$row]),
        ['campaign_id' => $campaign->id],
    );

    expect($required[$row->id])->toBe([$productCategory->id]);
});

it('0110 AC-014: a row without products falls back to its campaign categories', function () {
    $function = BusinessFunction::factory()->create();
    $campaignCategory = categoryWithFunction($function);

    $campaign = campaignClassifiedAs($campaignCategory);

    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);

    $required = app(ImportRowCompetence::class)->requiredByRow(
        collect([$row]),
        ['campaign_id' => $campaign->id],
    );

    expect($required[$row->id])->toBe([$campaignCategory->id]);
});

it('0110 AC-014: a row with neither products nor a campaign requires nothing', function () {
    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->for($run, 'importRun')->create(['product_ids' => null]);

    $required = app(ImportRowCompetence::class)->requiredByRow(collect([$row]), []);

    expect($required[$row->id])->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-015 — the lead and the offer requirements (INV-1).
// ---------------------------------------------------------------------------

it('0110 AC-015: a lead requires its products of interest categories, falling back to its campaign', function () {
    $function = BusinessFunction::factory()->create();
    $productCategory = categoryWithFunction($function);
    $campaignCategory = categoryWithFunction($function);

    $product = Product::factory()->create(['category_id' => $productCategory->id]);
    $campaign = campaignClassifiedAs($campaignCategory);

    $withProducts = Lead::factory()->create(['campaign_id' => $campaign->id]);
    $withProducts->productsOfInterest()->sync([$product->id]);
    $withoutProducts = Lead::factory()->create(['campaign_id' => $campaign->id]);

    $required = app(LeadCompetence::class)->requiredByLead([$withProducts->id, $withoutProducts->id]);

    expect($required[$withProducts->id])->toBe([$productCategory->id]);
    expect($required[$withoutProducts->id])->toBe([$campaignCategory->id]);
});

it('0110 AC-015: an offer requires the product categories of its opportunity lines', function () {
    $function = BusinessFunction::factory()->create();
    $categoryOne = categoryWithFunction($function);
    $categoryTwo = categoryWithFunction($function);

    $opportunity = Opportunity::factory()->create();
    foreach ([$categoryOne, $categoryTwo] as $category) {
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);
    }
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $required = app(QuoteCompetence::class)->requiredByQuote([$quote->id]);

    expect($required[$quote->id])->toEqualCanonicalizing([$categoryOne->id, $categoryTwo->id]);
});
