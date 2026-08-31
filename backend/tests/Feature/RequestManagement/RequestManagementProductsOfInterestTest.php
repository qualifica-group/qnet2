<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * REQUIREMENT CHANGED WHOLESALE (spec 0086, AC-022): PATCH
 * /api/request-management/{quote} no longer accepts `products_of_interest`
 * at all — UpdateRequestRequest::rules() declares no rule for that key any
 * more (see its own docblock), so the endpoint's `products_of_interest`
 * write channel this file used to exercise (persist/replace/auto-add
 * category/coherence-on-write) is GONE. The grid's replacement column,
 * `offer_lines`, is read-only (AC-021), derived from the Offerta's own
 * REVENUE lines — editing "prodotti di interesse" is now exclusively an
 * Opportunities-form concern (`POST /api/request-management` still accepts
 * it at CREATION time only, data_contract; covered by
 * RequestManagementProductLinesTest's creation-channel cases).
 *
 * Every test below that used to assert a PATCH `products_of_interest` WRITE
 * is replaced by this file's single concern now: that key is a no-op on this
 * endpoint — ignored, never rejected, never mutating anything (AC-022's
 * "ignorata o rifiutata" is satisfied by ignoring).
 */
uses(RefreshDatabase::class);

if (! function_exists('productInterestActor')) {
    function productInterestActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('request-management.update');

        return $user;
    }
}

if (! function_exists('productInterestQuote')) {
    function productInterestQuote(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('productInterestCategory')) {
    /** A category with its own business function, plus one product inside it. */
    function productInterestCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

it('AC-022: PATCH products_of_interest is a no-op — an empty collection stays empty, 200 OK', function () {
    $actor = productInterestActor();
    $quote = productInterestQuote($actor);
    $category = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $quote->opportunity_id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'products_of_interest' => [$product->id],
    ])->assertOk();

    expect($quote->opportunity->fresh()->productsOfInterest)->toHaveCount(0);
    $this->assertDatabaseMissing('opportunity_product', [
        'opportunity_id' => $quote->opportunity_id,
        'product_id' => $product->id,
    ]);
});

it('AC-022: PATCH products_of_interest never detaches an already-persisted collection', function () {
    $actor = productInterestActor();
    $quote = productInterestQuote($actor);
    $category = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $quote->opportunity_id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $kept = Product::factory()->create(['category_id' => $category->id]);
    $quote->opportunity->productsOfInterest()->sync([$kept->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'products_of_interest' => [],
    ])->assertOk();

    expect($quote->opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$kept->id]);
});

it('AC-022: an unknown product id in products_of_interest never 422s — the key is never validated', function () {
    $actor = productInterestActor();
    $quote = productInterestQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'products_of_interest' => [999999],
    ])->assertOk();
});

it('AC-022: the coherence check on a product_lines change still runs against the PERSISTED products of interest, ignoring a same-request products_of_interest key', function () {
    $actor = productInterestActor();
    $quote = productInterestQuote($actor);
    $ownCategory = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $quote->opportunity_id,
        'business_function_id' => $ownCategory->business_function_id,
        'product_category_id' => $ownCategory->id,
    ]);
    $persistedProduct = Product::factory()->create(['category_id' => $ownCategory->id]);
    $quote->opportunity->productsOfInterest()->sync([$persistedProduct->id]);
    $replacement = productInterestCategory();
    Sanctum::actingAs($actor);

    // Even though the payload also submits a compatible products_of_interest,
    // the key is ignored — the coherence check sees the PERSISTED product
    // still tied to $ownCategory, which product_lines is about to drop.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'product_lines' => [[
            'business_function_id' => $replacement->business_function_id,
            'product_category_id' => $replacement->id,
        ]],
        'products_of_interest' => [Product::factory()->create(['category_id' => $replacement->id])->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect($quote->opportunity->fresh()->productLines()->where('product_category_id', $ownCategory->id)->exists())->toBeTrue();
});
