<?php

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Per-product "Attivo" flag (spec 0366): write path, mass action, pickers,
 * link validation and grid column.
 */
uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function activeProductPayload(ProductCategory $category, array $overrides = []): array
{
    return ['name' => 'Widget', 'cost' => 10, 'price' => 20, 'product_type' => 'SERVICE', 'category_id' => $category->id, ...$overrides];
}

/** @return array{0: User, 1: BusinessFunction, 2: ProductCategory} */
function activeProductOpportunityContext(): array
{
    foreach (['opportunities.create', 'opportunities.update', 'opportunities.viewAll'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['opportunities.create', 'opportunities.update', 'opportunities.viewAll']);
    $function = BusinessFunction::factory()->create();

    return [$actor, $function, ProductCategory::factory()->create(['business_function_id' => $function->id])];
}

it('AC-001: POST creates an active product by default and an inactive one on request', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(productUserWith(['create']));

    $this->postJson('/api/products', activeProductPayload($category))->assertCreated()->assertJsonPath('data.is_active', true);
    $id = $this->postJson('/api/products', activeProductPayload($category, ['is_active' => false]))
        ->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->json('data.id');

    expect(Product::query()->find($id)->is_active)->toBeFalse();
});

it('AC-002: PATCH persists the flag, GET exposes it, a non boolean is rejected', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['view', 'update']));

    $this->patchJson("/api/products/{$product->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.is_active', false);
    $this->patchJson("/api/products/{$product->id}", ['is_active' => 'maybe'])->assertJsonValidationErrors('is_active');

    expect($product->fresh()->is_active)->toBeFalse();
});

it('AC-004: for-select omits an inactive product from items and total and hydrates it with ids[]', function () {
    $category = ProductCategory::factory()->create();
    $active = Product::factory()->create(['category_id' => $category->id]);
    $inactive = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
    Sanctum::actingAs(productUserWith(['viewAny']));

    $response = $this->getJson('/api/products/for-select')->assertOk();
    expect(collect($response->json('items'))->pluck('id')->all())->toBe([$active->id])
        ->and($response->json('pagination.total'))->toBe(1);

    $hydrated = $this->getJson('/api/products/for-select?ids[]='.$inactive->id)->assertOk();
    expect(collect($hydrated->json('items'))->pluck('id'))->toContain($inactive->id)
        ->and($hydrated->json('pagination.total'))->toBe(1);
});

it('AC-005: a new link to an inactive product is rejected, the one already saved stays valid', function () {
    [$actor, $function, $category] = activeProductOpportunityContext();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $lines = [['business_function_id' => $function->id, 'product_category_id' => $category->id]];
    $payload = ['name' => 'Opportunity', 'registry_id' => Registry::factory()->create()->id, 'supervisor_id' => User::factory()->create()->id, 'product_lines' => $lines];
    Sanctum::actingAs($actor);

    $id = $this->postJson('/api/opportunities', [...$payload, 'products_of_interest' => [$product->id]])->assertCreated()->json('data.id');
    $product->update(['is_active' => false]);

    $this->postJson('/api/opportunities', [...$payload, 'registry_id' => Registry::factory()->create()->id, 'products_of_interest' => [$product->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors', ['products_of_interest.0' => [__('This product is not active.')]]);

    $this->patchJson("/api/opportunities/{$id}", ['products_of_interest' => [$product->id]])->assertOk();
});

it('AC-006: an active product of an inactive category keeps the category message', function () {
    [$actor, $function, $category] = activeProductOpportunityContext();
    $product = Product::factory()->create(['category_id' => ProductCategory::factory()->create(['is_active' => false])->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', [
        'name' => 'Opportunity',
        'registry_id' => Registry::factory()->create()->id,
        'supervisor_id' => User::factory()->create()->id,
        'product_lines' => [['business_function_id' => $function->id, 'product_category_id' => $category->id]],
        'products_of_interest' => [$product->id],
    ])->assertStatus(422)->assertJsonPath('errors', ['products_of_interest.0' => [__('This product belongs to an inactive category.')]]);
});

it('AC-007: the mass action deactivates and reactivates the products, one activity entry each', function () {
    $ids = Product::factory()->count(3)->create()->pluck('id')->all();
    Sanctum::actingAs(productUserWith(['update']));

    $this->postJson('/api/products/activation', ['product_ids' => $ids, 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.updated', 3);

    expect(Product::query()->whereIn('id', $ids)->where('is_active', false)->count())->toBe(3)
        ->and(Activity::query()->where('subject_type', (new Product)->getMorphClass())->whereIn('subject_id', $ids)->where('event', 'updated')->count())->toBe(3);

    $this->postJson('/api/products/activation', ['product_ids' => $ids, 'is_active' => true])->assertOk();

    expect(Product::query()->whereIn('id', $ids)->where('is_active', true)->count())->toBe(3);
});

it('AC-007: the mass action is forbidden without products.update', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['view']));

    $this->postJson('/api/products/activation', ['product_ids' => [$product->id], 'is_active' => false])->assertForbidden();

    expect($product->fresh()->is_active)->toBeTrue();
});

it('AC-007: the mass action is forbidden when the role matrix locks the is_active field', function () {
    $product = Product::factory()->create();
    $actor = productUserWith(['update']);
    $role = Role::create(['name' => 'catalog-editor']);
    $actor->assignRole($role);
    RoleFieldPermission::query()->create([
        'role_id' => $role->id, 'resource' => 'products', 'field' => 'is_active',
        'visible' => true, 'editable' => false, 'required' => false,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/products/activation', ['product_ids' => [$product->id], 'is_active' => false])->assertForbidden();

    expect($product->fresh()->is_active)->toBeTrue();
});

it('AC-007: the mass action validates ids and state', function () {
    $product = Product::factory()->create();
    Sanctum::actingAs(productUserWith(['update']));

    $this->postJson('/api/products/activation', ['product_ids' => [], 'is_active' => false])->assertJsonValidationErrors('product_ids');
    $this->postJson('/api/products/activation', ['product_ids' => [$product->id, 999999], 'is_active' => false])->assertJsonValidationErrors('product_ids.1');
    $this->postJson('/api/products/activation', ['product_ids' => [$product->id]])->assertJsonValidationErrors('is_active');
    $this->postJson('/api/products/activation', ['product_ids' => range(1, 501), 'is_active' => false])->assertJsonValidationErrors('product_ids');
});

it('AC-008: the grid exposes a sortable, filterable is_active column; the "No" filter returns the inactive ones', function () {
    $off = Product::factory()->create(['is_active' => false]);
    Product::factory()->create();
    Sanctum::actingAs(productUserWith(['viewAny']));

    $column = collect($this->getJson('/api/tables/products/columns')->assertOk()->json('data.columns'))->firstWhere('id', 'is_active');

    expect($column['type'])->toBe('boolean')
        ->and($column['filterType'])->toBe('boolean')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue();

    $rows = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('id')->all())->toBe([$off->id])
        ->and($rows[0]['is_active'])->toBeFalse();
});
