<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * ENFORCEMENT of `single_quote_per_opportunity` (user directive 2026-08-07):
 * an opportunity covered by a flagged product category accepts ONE quote. The
 * flag's own inheritance contract is covered by
 * tests/Feature/ProductCategories/ProductCategorySingleQuoteTest.php.
 */
uses(RefreshDatabase::class);

if (! function_exists('singleQuoteActor')) {
    function singleQuoteActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.create', 'quotes.update']);

        QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

        Sanctum::actingAs($user);

        return $user;
    }
}

if (! function_exists('singleQuoteOpportunity')) {
    /** An opportunity whose one covered category carries the flag as given. */
    function singleQuoteOpportunity(bool $singleQuote): Opportunity
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'single_quote_per_opportunity' => $singleQuote,
        ]);

        $opportunity = Opportunity::factory()->create();
        $opportunity->productLines()->create([
            'business_function_id' => $category->business_function_id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('singleQuoteOfferLine')) {
    /**
     * Spec 0102: POST /api/quotes now requires at least one offer_lines row.
     * A product from a category $opportunity ALREADY covers clears
     * OpportunityProductLineCoverage::ensure() as a no-op.
     *
     * @return array<int, array<string, int>>
     */
    function singleQuoteOfferLine(Opportunity $opportunity): array
    {
        $categoryId = $opportunity->productLines()->value('product_category_id');
        $product = Product::factory()->create(['category_id' => $categoryId]);

        return [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]];
    }
}

if (! function_exists('singleQuoteRevenueProduct')) {
    /**
     * For an opportunity with NO covered category yet: a category with an
     * EFFECTIVE business function so the auto-add coverage path never trips
     * the 422 guard.
     */
    function singleQuoteRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

it('the FIRST quote on a flagged opportunity is accepted', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);

    $this->postJson('/api/quotes', [
        'title' => 'Prima offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => singleQuoteOfferLine($opportunity),
    ])->assertCreated();
});

it('a SECOND quote on a flagged opportunity is rejected, naming opportunity_id', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->postJson('/api/quotes', ['title' => 'Seconda offerta', 'opportunity_id' => $opportunity->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunity_id');

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(1);
});

it('an unflagged opportunity still takes several quotes', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(false);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->postJson('/api/quotes', [
        'title' => 'Seconda offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => singleQuoteOfferLine($opportunity),
    ])->assertCreated();

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(2);
});

it('an opportunity with no product line is never capped (rule indeterminate)', function () {
    singleQuoteActor();
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $product = singleQuoteRevenueProduct();

    $this->postJson('/api/quotes', [
        'title' => 'Seconda offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated();
});

it('grandfathering: an already-multi-quote opportunity stays editable once the flag is turned on', function () {
    singleQuoteActor();
    $opportunity = singleQuoteOpportunity(true);
    $first = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    $this->patchJson("/api/quotes/{$first->id}", ['title' => 'Titolo corretto'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Titolo corretto');
});

// ---------------------------------------------------------------------------
// read side — the flag the Offerte panel disables its "Crea Offerta" on
// ---------------------------------------------------------------------------

it('GET opportunity exposes single_quote_per_opportunity, true only on a capped branch', function () {
    Permission::findOrCreate('opportunities.view');
    $actor = User::factory()->create();
    $actor->givePermissionTo('opportunities.view');
    Sanctum::actingAs($actor);

    $capped = singleQuoteOpportunity(true);
    $free = singleQuoteOpportunity(false);
    $bare = Opportunity::factory()->create();

    $this->getJson("/api/opportunities/{$capped->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', true);

    $this->getJson("/api/opportunities/{$free->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', false);

    // No product line at all: the rule is indeterminate, never applied.
    $this->getJson("/api/opportunities/{$bare->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', false);
});
