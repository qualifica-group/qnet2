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
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0111 D-2 — the competence rule read PER ROW (AC-011..AC-015), on top
 * of the ONE deroga left by rev.2 (INV-4a/D-9a) and of spec 0110's three
 * requirement resolvers (INV-1, unchanged: AC-014/AC-015 of that spec).
 */
if (! function_exists('competentUser')) {
    /**
     * A user whose employment profile carries one competence row per given
     * category, all paired with $function.
     */
    function competentUser(BusinessFunction $function, ProductCategory ...$categories): User
    {
        $user = User::factory()->create();

        EmploymentProfile::factory()->for($user)->competentIn($function, ...$categories)->create();

        return $user;
    }
}

if (! function_exists('rowlessUser')) {
    /** A user with an employment profile but no competence row: not a candidate since rev.2 (D-9). */
    function rowlessUser(): User
    {
        $user = User::factory()->create();

        EmploymentProfile::factory()->for($user)->create();

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
// AC-011 / AC-012 — the row carries BOTH halves, and both must line up.
// ---------------------------------------------------------------------------

it('0111 AC-011: a row pairing the required category with its effective function makes the user competent', function () {
    $function = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);

    $user = competentUser($function, $category);

    expect(app(OperatorCompetence::class)->competent([$user->id], [$category->id]))
        ->toBe([$user->id]);
});

it('0111 AC-012: a row pairing the required category with the WRONG function excludes the user', function () {
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);

    $user = competentUser($otherFunction, $category);

    expect(app(OperatorCompetence::class)->competent([$user->id], [$category->id]))->toBe([]);
    expect(app(OperatorCompetence::class)->competentUserIds([$category->id]))->toBe([]);
});

it('0111 AC-012: a row on another category of the right function does not cover the required one', function () {
    $function = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);
    $otherCategory = categoryWithFunction($function);

    $user = competentUser($function, $otherCategory);

    expect(app(OperatorCompetence::class)->competent([$user->id], [$category->id]))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-013 — a row on the parent covers the branch below it (INV-2).
// ---------------------------------------------------------------------------

it('0111 AC-013: a row on the parent category covers its descendants', function () {
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
// D-2 — what the single-function profile of spec 0110 could not express.
// ---------------------------------------------------------------------------

it('0111 D-2: two rows on two different functions make the same user competent on both', function () {
    $firstFunction = BusinessFunction::factory()->create();
    $secondFunction = BusinessFunction::factory()->create();
    $firstCategory = categoryWithFunction($firstFunction);
    $secondCategory = categoryWithFunction($secondFunction);

    $user = User::factory()->create();
    EmploymentProfile::factory()
        ->for($user)
        ->competentIn($firstFunction, $firstCategory)
        ->competentIn($secondFunction, $secondCategory)
        ->create();

    $competence = app(OperatorCompetence::class);

    expect($competence->competent([$user->id], [$firstCategory->id]))->toBe([$user->id]);
    expect($competence->competent([$user->id], [$secondCategory->id]))->toBe([$user->id]);
});

it('0111 D-2: a category whose chain carries no function is decided by the category coverage alone', function () {
    $function = BusinessFunction::factory()->create();
    $functionlessCategory = ProductCategory::factory()->create([
        'business_function_id' => null,
        'parent_id' => null,
    ]);

    $user = competentUser($function, $functionlessCategory);

    expect(app(OperatorCompetence::class)->competent([$user->id], [$functionlessCategory->id]))
        ->toBe([$user->id]);
});

it('0111 D-2: the batch stays constant-query, whatever the number of users and requirements', function () {
    $function = BusinessFunction::factory()->create();
    $otherFunction = BusinessFunction::factory()->create();
    $parent = categoryWithFunction($function);
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $otherCategory = categoryWithFunction($otherFunction);

    $candidateIds = collect([
        competentUser($function, $parent),
        competentUser($function, $child),
        competentUser($otherFunction, $otherCategory),
    ])->pluck('id')->all();

    $competence = app(OperatorCompetence::class);

    DB::enableQueryLog();
    $competence->competentByRequirement($candidateIds, [
        'first' => [$parent->id],
        'second' => [$child->id],
        'third' => [$otherCategory->id],
    ]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Profiles, their rows, the two the taxonomy needs (categories +
    // functions) and the parent map: five reads for the WHOLE batch, never
    // one per record or per user.
    expect($queries)->toHaveCount(5);
});

// ---------------------------------------------------------------------------
// AC-014 rev.2 / AC-015 — the revoked deroga, and the one still standing.
// ---------------------------------------------------------------------------

it('0111 AC-014 rev.2: a user with no competence row is NOT a candidate for a record requiring a category', function () {
    $function = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);

    $rowless = rowlessUser();
    $noProfileAtAll = User::factory()->create();
    $covering = competentUser($function, $category);
    $configuredElsewhere = competentUser($function, categoryWithFunction($function));

    $candidates = [$rowless->id, $noProfileAtAll->id, $covering->id, $configuredElsewhere->id];

    // AC-029: the profile-less user goes through the same reading as the
    // rowless one, without erroring on the missing profile.
    expect(app(OperatorCompetence::class)->competent($candidates, [$category->id]))
        ->toBe([$covering->id]);
});

it('0111 AC-015: a record requiring no category leaves every candidate in place', function () {
    $function = BusinessFunction::factory()->create();
    $category = categoryWithFunction($function);
    $configured = competentUser($function, $category);
    $plain = User::factory()->create();

    expect(app(OperatorCompetence::class)->competent([$configured->id, $plain->id], []))
        ->toBe([$configured->id, $plain->id]);
    // INV-4a (D-9a) is decided by competent(), not by the inclusion set:
    // asked for nobody's category, the set is legitimately empty.
    expect(app(OperatorCompetence::class)->competentUserIds([]))->toBe([]);
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
