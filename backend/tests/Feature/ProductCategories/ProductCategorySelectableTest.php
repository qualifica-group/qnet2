<?php

use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\Opportunity;
use App\Models\PipelineStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `is_selectable` (spec 0074): whether a category may be picked as a
 * classification TARGET. Per-node, never inherited — an unselectable node
 * stays a valid parent. The flag hides the category from the for-select feed
 * and makes every destination endpoint reject it, EXCEPT for the value
 * already persisted on the record being updated (D-3b).
 */
uses(RefreshDatabase::class);

if (! function_exists('selectableCategoryActorWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. `products.create`
     */
    function selectableCategoryActorWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// the flag itself — AC-001..AC-004
// ---------------------------------------------------------------------------

it('create: omitting is_selectable yields a selectable category (AC-001)', function () {
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', ['name' => 'Default'])
        ->assertCreated()
        ->assertJsonPath('data.is_selectable', true);
});

it('create/update: is_selectable is persisted and never propagates to children (AC-002)', function () {
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.create', 'product-categories.update']));

    $container = $this->postJson('/api/product-categories', ['name' => 'Container', 'is_selectable' => false])
        ->assertCreated()
        ->assertJsonPath('data.is_selectable', false)
        ->json('data.id');

    // A child of an unselectable parent is selectable by default: the flag is
    // per-node, unlike requires_quote.
    $this->postJson('/api/product-categories', ['name' => 'Leaf', 'parent_id' => $container])
        ->assertCreated()
        ->assertJsonPath('data.is_selectable', true);

    $this->patchJson("/api/product-categories/{$container}", ['is_selectable' => true])
        ->assertOk()
        ->assertJsonPath('data.is_selectable', true);
});

it('show: exposes is_selectable (AC-003)', function () {
    $category = ProductCategory::factory()->create(['is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.view']));

    $this->getJson("/api/product-categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('data.is_selectable', false);
});

it('tree: carries is_selectable and still lists unselectable nodes (AC-004)', function () {
    $container = ProductCategory::factory()->create(['name' => 'Container', 'is_selectable' => false]);
    ProductCategory::factory()->childOf($container)->create(['name' => 'Leaf']);
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.viewAny']));

    $tree = $this->getJson('/api/product-categories/tree')->assertOk()->json('data');

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['is_selectable'])->toBeFalse()
        ->and($tree[0]['children'][0]['is_selectable'])->toBeTrue();
});

it('table: rows carry the per-node flag', function () {
    $container = ProductCategory::factory()->create(['name' => 'Container', 'is_selectable' => false]);
    ProductCategory::factory()->childOf($container)->create(['name' => 'Leaf']);
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.viewAny']));

    $rows = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->keyBy('name');

    expect($rows['Container']['is_selectable'])->toBeFalse()
        ->and($rows['Leaf']['is_selectable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// for-select — AC-005/AC-006
// ---------------------------------------------------------------------------

it('for-select: omits unselectable categories and excludes them from the total (AC-005)', function () {
    ProductCategory::factory()->create(['name' => 'Pickable']);
    $container = ProductCategory::factory()->create(['name' => 'Container', 'is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.viewAny']));

    $response = $this->getJson('/api/product-categories/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('id')->all())->not->toContain($container->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('for-select: ids[] still hydrates an unselectable category (AC-006)', function () {
    $container = ProductCategory::factory()->create(['name' => 'Container', 'is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['product-categories.viewAny']));

    $items = $this->getJson("/api/product-categories/for-select?ids[]={$container->id}")
        ->assertOk()
        ->json('items');

    expect(collect($items)->pluck('id')->all())->toContain($container->id);
});

// ---------------------------------------------------------------------------
// products — AC-007..AC-009
// ---------------------------------------------------------------------------

it('products create: an unselectable category is rejected (AC-007)', function () {
    $container = ProductCategory::factory()->create(['is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['products.create']));

    $this->postJson('/api/products', [
        'name' => 'Widget', 'cost' => 10, 'price' => 20, 'product_type' => 'SERVICE',
        'category_id' => $container->id,
    ])->assertStatus(422)->assertJsonValidationErrors('category_id');

    expect(Product::count())->toBe(0);
});

it('products update: resubmitting the product OWN category unchanged passes (AC-008)', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $category->update(['is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['products.update']));

    $this->patchJson("/api/products/{$product->id}", [
        'name' => 'Renamed', 'category_id' => $category->id,
    ])->assertOk();
});

it('products update: moving to a DIFFERENT unselectable category is rejected (AC-009)', function () {
    $product = Product::factory()->create(['category_id' => ProductCategory::factory()->create()->id]);
    $container = ProductCategory::factory()->create(['is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['products.update']));

    $this->patchJson("/api/products/{$product->id}", ['category_id' => $container->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

// ---------------------------------------------------------------------------
// opportunities / request management product lines — AC-010/AC-011
// ---------------------------------------------------------------------------

it('opportunities create: an unselectable category on a product line is rejected (AC-010)', function () {
    $businessFunction = BusinessFunction::factory()->create();
    $container = ProductCategory::factory()->create([
        'business_function_id' => $businessFunction->id, 'is_selectable' => false,
    ]);
    Sanctum::actingAs(selectableCategoryActorWith(['opportunities.create']));

    $this->postJson('/api/opportunities', [
        'name' => 'Rejected lines',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $container->id],
        ],
        'products_of_interest' => [Product::factory()->create(['category_id' => $container->id])->id],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.product_category_id');

    expect(Opportunity::count())->toBe(0);
});

it('opportunities update: resubmitting the OWN lines unchanged passes (AC-011)', function () {
    $businessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
    Sanctum::actingAs(selectableCategoryActorWith(['opportunities.create', 'opportunities.update']));

    $lines = [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]];

    $opportunityId = $this->postJson('/api/opportunities', [
        'name' => 'Kept lines',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => $lines,
        'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
    ])->assertCreated()->json('data.id');

    $category->update(['is_selectable' => false]);

    $this->patchJson("/api/opportunities/{$opportunityId}", ['product_lines' => $lines])->assertOk();
});

// ---------------------------------------------------------------------------
// projects / campaigns / commission configurations — AC-012
// ---------------------------------------------------------------------------

it('projects create: an unselectable category is rejected (AC-012)', function () {
    $function = BusinessFunction::factory()->create();
    $container = ProductCategory::factory()->create([
        'business_function_id' => $function->id, 'is_selectable' => false,
    ]);
    Sanctum::actingAs(selectableCategoryActorWith(['projects.create']));

    $this->postJson('/api/projects', [
        'name' => 'Rejected',
        'pipeline_status_id' => PipelineStatus::factory()->create()->id,
        'country_id' => Country::factory()->create()->id,
        'business_function_id' => $function->id,
        'product_category_id' => $container->id,
        'start_date' => '2026-01-01',
    ])->assertStatus(422)->assertJsonValidationErrors('product_category_id');
});

it('campaigns create: an unselectable category is rejected (AC-012)', function () {
    $function = BusinessFunction::factory()->create();
    $container = ProductCategory::factory()->create([
        'business_function_id' => $function->id, 'is_selectable' => false,
    ]);
    Sanctum::actingAs(selectableCategoryActorWith(['campaigns.create']));

    $this->postJson('/api/campaigns', [
        'project_id' => null,
        'name' => 'Rejected',
        'business_function_id' => $function->id,
        'product_category_id' => $container->id,
        'country_id' => Country::factory()->create()->id,
        'start_date' => '2026-01-01',
    ])->assertStatus(422)->assertJsonValidationErrors('product_category_id');
});

it('commission configurations create: an unselectable category is rejected (AC-012)', function () {
    $container = ProductCategory::factory()->create(['is_selectable' => false]);
    Sanctum::actingAs(selectableCategoryActorWith(['commission-configurations.create']));

    $this->postJson('/api/commission-configurations', [
        'name' => 'Rejected rule',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT_CATEGORY',
        'product_category_id' => $container->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ])->assertStatus(422)->assertJsonValidationErrors('product_category_id');
});
