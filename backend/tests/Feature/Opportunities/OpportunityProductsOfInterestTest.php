<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * "Prodotti di interesse" (user directive 2026-07-22) on the opportunities
 * CRUD: same collection, same writer and same cross-category rule as the
 * request-management work panel — the two write channels may never diverge.
 */
uses(RefreshDatabase::class);

if (! function_exists('productsOfInterestActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productsOfInterestActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('productsOfInterestMandatoryFks')) {
    /**
     * @return array{registry_id: int, supervisor_id: int}
     */
    function productsOfInterestMandatoryFks(): array
    {
        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
        ];
    }
}

if (! function_exists('productsOfInterestCategory')) {
    function productsOfInterestCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

it('create: products_of_interest persists and is exposed on the detail resource', function () {
    $actor = productsOfInterestActor(['create']);
    $category = productsOfInterestCategory();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(productsOfInterestMandatoryFks(), [
        'name' => 'With products of interest',
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [$product->id],
    ]))->assertCreated();

    $response->assertJsonPath('data.products_of_interest.0.id', $product->id)
        ->assertJsonPath('data.products_of_interest.0.product_category.id', $category->id);
    $this->assertDatabaseHas('opportunity_product', [
        'opportunity_id' => $response->json('data.id'),
        'product_id' => $product->id,
    ]);
});

it('create: a product outside the submitted product_lines adds its own row (user directive 2026-07-22)', function () {
    $actor = productsOfInterestActor(['create']);
    $lineCategory = productsOfInterestCategory();
    $otherCategory = productsOfInterestCategory();
    $outsideProduct = Product::factory()->create(['category_id' => $otherCategory->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(productsOfInterestMandatoryFks(), [
        'name' => 'Cross-category pick',
        'product_lines' => [
            ['business_function_id' => $lineCategory->business_function_id, 'product_category_id' => $lineCategory->id],
        ],
        'products_of_interest' => [$outsideProduct->id],
    ]))->assertCreated();

    $this->assertDatabaseHas('opportunity_product_lines', [
        'opportunity_id' => $response->json('data.id'),
        'business_function_id' => $otherCategory->business_function_id,
        'product_category_id' => $otherCategory->id,
    ]);
    expect($response->json('data.product_lines'))->toHaveCount(2);
});

// Requirement CHANGED (user directive 2026-07-23): products_of_interest used
// to be optional — it is now mandatory on create, and never clearable.
it('create: omitting products_of_interest -> 422, no opportunity created (mandatory since 2026-07-23)', function () {
    $actor = productsOfInterestActor(['create']);
    $category = productsOfInterestCategory();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productsOfInterestMandatoryFks(), [
        'name' => 'No products of interest',
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect(Opportunity::count())->toBe(0);
});

it('create: products_of_interest: [] -> 422 (never clearable)', function () {
    $actor = productsOfInterestActor(['create']);
    $category = productsOfInterestCategory();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(productsOfInterestMandatoryFks(), [
        'name' => 'Empty products of interest',
        'product_lines' => [
            ['business_function_id' => $category->business_function_id, 'product_category_id' => $category->id],
        ],
        'products_of_interest' => [],
    ]))->assertStatus(422)->assertJsonValidationErrors('products_of_interest');

    expect(Opportunity::count())->toBe(0);
});

it('update: products_of_interest is an authoritative replace; omitting it leaves the collection untouched', function () {
    $actor = productsOfInterestActor(['update']);
    $opportunity = Opportunity::factory()->create();
    $category = productsOfInterestCategory();
    $kept = Product::factory()->create(['category_id' => $category->id]);
    $dropped = Product::factory()->create(['category_id' => $category->id]);
    $opportunity->productsOfInterest()->sync([$kept->id, $dropped->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'products_of_interest' => [$kept->id],
    ])->assertOk()->assertJsonCount(1, 'data.products_of_interest');

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['name' => 'Renamed'])->assertOk();

    expect($opportunity->fresh()->productsOfInterest->pluck('id')->all())->toBe([$kept->id]);
});

it('update: an unknown product id -> 422', function () {
    $actor = productsOfInterestActor(['update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", [
        'products_of_interest' => [999999],
    ])->assertStatus(422)->assertJsonValidationErrors('products_of_interest.0');
});
