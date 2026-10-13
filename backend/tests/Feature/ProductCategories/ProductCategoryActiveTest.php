<?php

use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\CategoryActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `is_active` (spec 0208): the node's OWN flag, never rewritten on children;
 * the EFFECTIVE activity (inactive ancestor deactivates the subtree) is
 * resolved in batch by CategoryActivity.
 */
uses(RefreshDatabase::class);

if (! function_exists('activeCategoryActorWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. `product-categories.create`
     */
    function activeCategoryActorWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

it('create: omitting is_active yields an active category (AC-001)', function () {
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.create']));

    $this->postJson('/api/product-categories', ['name' => 'Default'])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true);
});

it('create/update: is_active is persisted and never propagates to children (AC-002)', function () {
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.create', 'product-categories.update']));

    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $this->postJson('/api/product-categories', ['name' => 'Off', 'is_active' => false])
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);

    $this->patchJson("/api/product-categories/{$root->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($root->fresh()->is_active)->toBeFalse()
        ->and($child->fresh()->is_active)->toBeTrue();
});

it('update: a non-boolean is_active is rejected with 422', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.update']));

    $this->patchJson("/api/product-categories/{$category->id}", ['is_active' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});

it('update: a user without the update permission gets 403', function () {
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.view']));

    $this->patchJson("/api/product-categories/{$category->id}", ['is_active' => false])->assertForbidden();
});

it('show and tree: expose is_active and the tree keeps inactive nodes and their children (AC-003)', function () {
    $root = ProductCategory::factory()->create(['name' => 'Root', 'is_active' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.view', 'product-categories.viewAny']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $response = $this->getJson('/api/product-categories/tree')->assertOk();
    $nodes = collect($response->json('data'));

    expect($nodes->firstWhere('id', $root->id)['is_active'])->toBeFalse()
        ->and($nodes->firstWhere('id', $root->id)['children'][0]['id'])->toBe($child->id)
        ->and($nodes->firstWhere('id', $root->id)['children'][0]['is_active'])->toBeTrue();
});

it('grid: exposes a sortable, filterable is_active column and the "No" filter returns the own-false rows (AC-016)', function () {
    $off = ProductCategory::factory()->create(['name' => 'Off', 'is_active' => false]);
    ProductCategory::factory()->create(['name' => 'On']);
    Sanctum::actingAs(activeCategoryActorWith(['product-categories.viewAny']));

    $column = collect($this->getJson('/api/tables/product-categories/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'is_active');

    expect($column['type'])->toBe('boolean')
        ->and($column['filterType'])->toBe('boolean')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue();

    $rows = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['is_active' => ['filterType' => 'boolean', 'filter' => false]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('id')->all())->toBe([$off->id])
        ->and($rows[0]['is_active'])->toBeFalse();
});

it('CategoryActivity: an inactive root deactivates every descendant even with their own flag true', function () {
    $root = ProductCategory::factory()->create(['is_active' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['is_active' => true]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['is_active' => true]);
    $other = ProductCategory::factory()->create();

    $activity = app(CategoryActivity::class);

    expect($activity->inactiveCategoryIds())->toEqualCanonicalizing([$root->id, $child->id, $grandchild->id])
        ->and($activity->isActive($root->id))->toBeFalse()
        ->and($activity->isActive($child->id))->toBeFalse()
        ->and($activity->isActive($grandchild->id))->toBeFalse()
        ->and($activity->isActive($other->id))->toBeTrue();
});

it('CategoryActivity: an active node under an active parent is active, and an inactive leaf leaves siblings alone', function () {
    $root = ProductCategory::factory()->create();
    $active = ProductCategory::factory()->childOf($root)->create();
    $inactiveLeaf = ProductCategory::factory()->childOf($root)->create(['is_active' => false]);

    $activity = app(CategoryActivity::class);

    expect($activity->isActive($root->id))->toBeTrue()
        ->and($activity->isActive($active->id))->toBeTrue()
        ->and($activity->isActive($inactiveLeaf->id))->toBeFalse()
        ->and($activity->inactiveCategoryIds())->toBe([$inactiveLeaf->id]);
});

it('CategoryActivity: resolves with a bounded number of queries and memoizes per instance', function () {
    $root = ProductCategory::factory()->create(['is_active' => false]);
    foreach (range(1, 5) as $_) {
        ProductCategory::factory()->childOf($root)->create();
    }

    $activity = app(CategoryActivity::class);

    DB::enableQueryLog();
    $activity->inactiveCategoryIds();
    $activity->inactiveCategoryIds();
    $activity->isActive($root->id);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(2);
});
