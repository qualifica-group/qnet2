<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * "Prodotti di interesse" (user directive 2026-07-22) on the operative work
 * panel: PATCH /api/request-management/{opportunity} replaces the whole
 * collection, and a product picked OUTSIDE the opportunity's product-line
 * categories also adds the matching funzione + categoria row — the rule the
 * panel warns about before unlocking the picker.
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

if (! function_exists('productInterestOpportunity')) {
    function productInterestOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
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

it('PATCH products_of_interest persists the collection and exposes it back', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $category = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$product->id],
    ])->assertOk();

    $response->assertJsonPath('data.products_of_interest.0.id', $product->id)
        ->assertJsonPath('data.products_of_interest.0.product_category.id', $category->id);
    $this->assertDatabaseHas('opportunity_product', [
        'opportunity_id' => $opportunity->id,
        'product_id' => $product->id,
    ]);
});

// Requirement CHANGED (user directive 2026-07-23): the collection is still an
// authoritative replace, but it is now MANDATORY — `[]` is rejected instead of
// clearing it, exactly like on the opportunities form.
it('PATCH products_of_interest is authoritative: a removed product is detached, [] is rejected', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $category = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $kept = Product::factory()->create(['category_id' => $category->id]);
    $dropped = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$kept->id, $dropped->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$kept->id],
    ])->assertOk()->assertJsonCount(1, 'data.products_of_interest');

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(1);
});

it('PATCH without products_of_interest leaves the collection untouched (sparse payload)', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $category = productInterestCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$product->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertOk();

    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$product->id]);
});

/**
 * REQUIREMENT CHANGED (user directive 2026-07-31). This channel used to ACCEPT
 * a product outside the request's categories and silently add the matching
 * funzione + categoria row (directive 2026-07-22). Now that the commercials
 * edit those rows themselves, the mismatch is REFUSED: the operator adds the
 * product category to the request, or drops the product. The opportunities
 * CRUD keeps the auto-add rule (OpportunityProductLineCoverage) — see its own
 * suite.
 */
it('a product OUTSIDE the request categories is refused, and no product line is added', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $ownCategory = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $ownCategory->business_function_id,
        'product_category_id' => $ownCategory->id,
    ]);
    $otherCategory = productInterestCategory();
    $outsideProduct = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$outsideProduct->id],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect($opportunity->fresh()->productLines)->toHaveCount(1);
    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(0);
});

/** ...and the same PATCH is accepted once it also brings the missing product category. */
it('accepts the outside product when the same PATCH adds its product category', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $ownCategory = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $ownCategory->business_function_id,
        'product_category_id' => $ownCategory->id,
    ]);
    $otherCategory = productInterestCategory();
    $outsideProduct = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$outsideProduct->id],
        'product_lines' => [
            ['business_function_id' => $ownCategory->business_function_id, 'product_category_id' => $ownCategory->id],
            ['business_function_id' => $otherCategory->business_function_id, 'product_category_id' => $otherCategory->id],
        ],
    ])->assertOk();

    expect($opportunity->fresh()->productLines)->toHaveCount(2);
    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$outsideProduct->id]);
});

it('a product whose category has no business function -> 422, nothing written', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => null, 'parent_id' => null]);
    $product = Product::factory()->create(['category_id' => $orphanCategory->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$product->id],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect($opportunity->fresh()->productsOfInterest)->toHaveCount(0);
    $this->assertDatabaseCount('opportunity_product_lines', 0);
});

it('an unknown product id -> 422 (exists rule)', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [999999],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest.0');
});

it('a products_of_interest change is logged on the opportunity activity feed', function () {
    $actor = productInterestActor();
    $opportunity = productInterestOpportunity($actor);
    $category = productInterestCategory();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $category->business_function_id,
        'product_category_id' => $category->id,
    ]);
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'products_of_interest' => [$product->id],
    ])->assertOk();

    $activity = Activity::query()->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->properties['attributes']['products_of_interest'])->toBe([$product->id]);
});
