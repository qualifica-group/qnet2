<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/product-categories (spec 0064 data_contract, M3,
// AC-001/002/003 + the D-2 multi-category count). Spec 0086: `requests_count`
// now counts the OFFERTE (Quote) in scope for the category, not opportunities.

uses(RefreshDatabase::class);

if (! function_exists('categoryTabsUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function categoryTabsUserWith(array $abilities): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteWithCategory')) {
    /**
     * A fresh Quote whose opportunity carries one product line against
     * $category.
     */
    function quoteWithCategory(ProductCategory $category): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create();
    }
}

// ---------------------------------------------------------------------------
// AC-001 — viewAll actor sees every category with a non-zero count, sorted by name
// ---------------------------------------------------------------------------

it('viewAll actor receives every category present in scope, ordered by name, with its own count (AC-001)', function () {
    $actor = categoryTabsUserWith(['viewAny', 'viewAll']);
    $categoryZ = ProductCategory::factory()->create(['name' => 'Zeta']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Alpha']);
    $categoryM = ProductCategory::factory()->create(['name' => 'Mu']);

    quoteWithCategory($categoryZ);
    quoteWithCategory($categoryA);
    quoteWithCategory($categoryA);
    quoteWithCategory($categoryM);

    // A category with no requests at all must never appear.
    ProductCategory::factory()->create(['name' => 'Empty']);

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/product-categories')->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.categories');

    $categories = $response->json('data.categories');
    expect(array_column($categories, 'name'))->toBe(['Alpha', 'Mu', 'Zeta']);

    $byName = collect($categories)->keyBy('name');
    expect($byName['Alpha']['requests_count'])->toBe(2)
        ->and($byName['Mu']['requests_count'])->toBe(1)
        ->and($byName['Zeta']['requests_count'])->toBe(1)
        ->and($byName['Alpha']['id'])->toBe($categoryA->id);
});

// ---------------------------------------------------------------------------
// AC-002 — operator scope: only the actor's own supervised offers count (D-3)
// ---------------------------------------------------------------------------

it('an operator without viewAll only sees the category of the offers they supervise (AC-002)', function () {
    $actor = categoryTabsUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Category A']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Category B']);

    // `operator_id` is deliberately NOT fillable (spec 0087, D-3) — written
    // only by QuoteManagerWriter, so a plain test fixture uses forceFill()
    // rather than a silently-discarded update().
    $ownRequest = quoteWithCategory($categoryA);
    $ownRequest->forceFill(['operator_id' => $actor->id])->save();

    // Someone else's offer on a DIFFERENT category — must not leak in.
    $otherRequest = quoteWithCategory($categoryB);
    $otherRequest->forceFill(['operator_id' => User::factory()->create()->id])->save();

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/product-categories')->assertOk();

    $response->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $categoryA->id)
        ->assertJsonPath('data.categories.0.requests_count', 1);
});

// ---------------------------------------------------------------------------
// AC-003 — authz gate
// ---------------------------------------------------------------------------

it('an actor without request-management.viewAny receives 403 (AC-003)', function () {
    $actor = categoryTabsUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/product-categories')->assertForbidden();
});

it('an unauthenticated caller receives 401', function () {
    $this->getJson('/api/request-management/product-categories')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// D-2 — a request spanning two categories counts in BOTH
// ---------------------------------------------------------------------------

it('a request with two product lines on different categories counts in both tabs (D-2)', function () {
    $actor = categoryTabsUserWith(['viewAny', 'viewAll']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Category A']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Category B']);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $categoryA->id,
    ]);
    OpportunityProductLine::factory()->create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => BusinessFunction::factory()->create()->id,
        'product_category_id' => $categoryB->id,
    ]);
    Quote::factory()->for($opportunity)->create();

    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/product-categories')->assertOk();

    $response->assertJsonCount(2, 'data.categories')
        ->assertJsonPath('data.categories.0.requests_count', 1)
        ->assertJsonPath('data.categories.1.requests_count', 1);
});

it('a request with two product lines of the SAME category counts once, not twice', function () {
    $actor = categoryTabsUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create(['name' => 'Category A']);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->count(2)->create([
        'opportunity_id' => $opportunity->id,
        'product_category_id' => $category->id,
    ]);
    Quote::factory()->for($opportunity)->create();

    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/product-categories')
        ->assertOk()
        ->assertJsonPath('data.categories.0.requests_count', 1);
});
