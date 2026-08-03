<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// meta.root_category_id / meta.management_mode (spec 0077 data_contract)
// ---------------------------------------------------------------------------

it('exposes meta.root_category_id/management_mode for a ROOT item (own values)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'management_mode' => 'single']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Formazione')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $root->id);

    expect($item['meta'])->toMatchArray([
        'root_category_id' => $root->id,
        'management_mode' => 'single',
    ]);
});

it('exposes meta.root_category_id/management_mode INHERITED from the branch root for a descendant', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=GOL - Lazio')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $child->id);

    expect($item['meta'])->toMatchArray([
        'root_category_id' => $root->id,
        'management_mode' => 'single',
    ]);
});

// ---------------------------------------------------------------------------
// root_category_id filter (INV-1 scoping)
// ---------------------------------------------------------------------------

it('root_category_id scopes the results to that root\'s subtree (own id + descendants)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create(['name' => 'Formazione']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio']);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['name' => 'GOL - Lazio - Roma']);
    $otherRoot = ProductCategory::factory()->create(['name' => 'Consulenza']);
    ProductCategory::factory()->childOf($otherRoot)->create(['name' => 'Consulenza - Altro']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/for-select?root_category_id={$root->id}&limit=100")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$root->id, $child->id, $grandchild->id]);
});

it('without root_category_id, the behaviour is unaffected (retrocompatible)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    ProductCategory::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select')->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

it('an invalid root_category_id -> 422 (exists)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select?root_category_id=999999')
        ->assertStatus(422)->assertJsonValidationErrors('root_category_id');
});

// ---------------------------------------------------------------------------
// batch resolution — never a query per row
// ---------------------------------------------------------------------------

it('resolves meta.management_mode for a full page in a bounded number of queries', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    ProductCategory::factory()->childOf($root)->count(10)->create();
    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $response = $this->getJson('/api/product-categories/for-select?limit=25')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($response->json('pagination.total'))->toBe(11)
        ->and($queryCount)->toBeLessThan(10);
});
