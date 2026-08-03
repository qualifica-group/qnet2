<?php

use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
// columns config
// ---------------------------------------------------------------------------

it('returns the 9 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = productCategoryUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/product-categories/columns')->assertForbidden();

    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/product-categories/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('product-categories')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'parent', 'description', 'business_function', 'requires_quote', 'is_selectable', 'management_mode', 'attributes_count', 'products_count', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['parent']['filterType'])->toBe('set')
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['business_function']['filterType'])->toBe('set')
        ->and($columns['business_function']['sortable'])->toBeFalse()
        ->and($columns['requires_quote']['type'])->toBe('boolean')
        ->and($columns['requires_quote']['filterType'])->toBe('boolean')
        ->and($columns['requires_quote']['sortable'])->toBeTrue()
        ->and($columns['is_selectable']['type'])->toBe('boolean')
        ->and($columns['is_selectable']['filterType'])->toBe('boolean')
        ->and($columns['is_selectable']['sortable'])->toBeTrue()
        ->and($columns['management_mode']['type'])->toBe('enum')
        ->and($columns['management_mode']['filterType'])->toBe('set')
        ->and($columns['management_mode']['sortable'])->toBeTrue()
        ->and($columns['management_mode']['options'])->toBe(['single', 'multiple'])
        ->and($columns['attributes_count']['filterType'])->toBe('number')
        ->and($columns['products_count']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// rows shape (no N+1: parent eager-loaded, counts via withCount)
// ---------------------------------------------------------------------------

it('rows expose id/name/parent{id,name}|null/description/counts/created_at + per-row actions', function () {
    $actor = productCategoryUserWith(['viewAny', 'view', 'update', 'delete']);
    $root = ProductCategory::factory()->create(['name' => 'Root']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    $attribute = Attribute::factory()->create(['name' => 'Color']);
    $child->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);
    $product = Product::factory()->create(['name' => 'Widget', 'category_id' => $child->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $rootRow = collect($response->json('items'))->firstWhere('name', 'Root');
    expect($rootRow['parent'])->toBeNull();

    $childRow = collect($response->json('items'))->firstWhere('name', 'Child');
    expect($childRow['parent'])->toBe(['id' => $root->id, 'name' => 'Root'])
        ->and($childRow['attributes_count'])->toBe(1)
        ->and($childRow['products_count'])->toBe(1)
        ->and($childRow['attributes'])->toBe([['id' => $attribute->id, 'name' => 'Color']])
        ->and($childRow['products'])->toBe([['id' => $product->id, 'name' => 'Widget']])
        ->and($childRow['actions'])->toEqualCanonicalizing(['view', 'edit', 'layout', 'delete']);
});

it('rows: the products tooltip list is capped at PRODUCT_TOOLTIP_LIST_LIMIT, products_count stays the real total', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $category = ProductCategory::factory()->create(['name' => 'Busy']);
    Product::factory()->count(101)->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $row = collect($response->json('items'))->firstWhere('name', 'Busy');
    expect($row['products_count'])->toBe(101)
        ->and($row['products'])->toHaveCount(100);
});

it('rows: no N+1 on the parent relation', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $root = ProductCategory::factory()->create();
    ProductCategory::factory()->childOf($root)->count(5)->create();
    Sanctum::actingAs($actor);

    ProductCategory::preventLazyLoading();

    $this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    ProductCategory::preventLazyLoading(false);
});

// ---------------------------------------------------------------------------
// derived `parent` filter/sort/distinct
// ---------------------------------------------------------------------------

it('filter: parent set filter narrows the rows via whereHas', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $rootA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $rootB = ProductCategory::factory()->create(['name' => 'Clothing']);
    ProductCategory::factory()->childOf($rootA)->create(['name' => 'Laptops']);
    ProductCategory::factory()->childOf($rootB)->create(['name' => 'Jackets']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['parent' => ['filterType' => 'set', 'values' => ['Electronics']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Laptops']);
});

it('sort: rows ordered by the derived parent name', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $rootA = ProductCategory::factory()->create(['name' => 'Zebra Root']);
    $rootB = ProductCategory::factory()->create(['name' => 'Alpha Root']);
    ProductCategory::factory()->childOf($rootA)->create(['name' => 'FromZebra']);
    ProductCategory::factory()->childOf($rootB)->create(['name' => 'FromAlpha']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'parent', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))
        ->whereIn('name', ['FromZebra', 'FromAlpha'])
        ->pluck('name')->values()->all();
    expect($names)->toBe(['FromAlpha', 'FromZebra']);
});

it('values: parent → distinct parent names, columnId outside the allow-list → 422', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $rootA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $rootB = ProductCategory::factory()->create(['name' => 'Clothing']);
    ProductCategory::factory()->childOf($rootA)->create();
    ProductCategory::factory()->childOf($rootB)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/values', ['columnId' => 'parent'])->assertOk();
    expect($response->json('data.values'))->toEqualCanonicalizing(['Electronics', 'Clothing']);

    $this->postJson('/api/tables/product-categories/values', ['columnId' => 'not_a_column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

// ---------------------------------------------------------------------------
// derived `business_function` column (spec 0023, AC-014)
// ---------------------------------------------------------------------------

it('rows: business_function shows the EFFECTIVE (own or inherited) function name', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $root = ProductCategory::factory()->create(['name' => 'Root', 'business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    $unrelated = ProductCategory::factory()->create(['name' => 'Unrelated']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $rows = collect($response->json('items'))->keyBy('name');

    expect($rows['Root']['business_function'])->toBe('Sales')
        ->and($rows['Child']['business_function'])->toBe('Sales')
        ->and($rows['Unrelated']['business_function'])->toBeNull();
});

it('filter: business_function set filter narrows the rows to those with the EFFECTIVE name, incl. inheriting ones', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $root = ProductCategory::factory()->create(['name' => 'Root', 'business_function_id' => $function->id]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'Child']);
    ProductCategory::factory()->create(['name' => 'Unrelated']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['business_function' => ['filterType' => 'set', 'values' => ['Sales']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Root', 'Child']);
});

it('values: business_function → distinct EFFECTIVE names', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    ProductCategory::factory()->childOf($root)->create();
    ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/values', ['columnId' => 'business_function'])->assertOk();
    expect($response->json('data.values'))->toBe(['Sales']);
});

it('business_function is declared non-sortable, rejected by the sortModel allow-list (422)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/product-categories/columns')->json('data.columns'))->keyBy('id');
    expect($columns['business_function']['sortable'])->toBeFalse();

    // A sortModel colId outside the sortable allow-list is rejected upfront
    // by the FormRequest (Rule::in(sortableColumnIds())) — it never reaches
    // a raw ORDER BY on the derived value.
    $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'business_function', 'sort' => 'asc']],
    ])->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');
});

// ---------------------------------------------------------------------------
// aggregate `attributes_count`/`products_count` filter + distinct
// ---------------------------------------------------------------------------

it('filter: products_count number condition narrows via a relation-count comparison', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $withProducts = ProductCategory::factory()->create(['name' => 'Busy']);
    Product::factory()->count(2)->create(['category_id' => $withProducts->id]);
    $withoutProducts = ProductCategory::factory()->create(['name' => 'Empty']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['products_count' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 1]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Busy']);
});

it('values: attributes_count → distinct counts, search-narrowed', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $category = ProductCategory::factory()->create();
    Attribute::factory()->count(2)->create()->each(
        fn (Attribute $attribute) => $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]),
    );
    ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/product-categories/values', ['columnId' => 'attributes_count'])->assertOk();
    expect($response->json('data.values'))->toEqualCanonicalizing(['0', '2']);

    $narrowed = $this->postJson('/api/tables/product-categories/values', ['columnId' => 'attributes_count', 'search' => '2'])->assertOk();
    expect($narrowed->json('data.values'))->toBe(['2']);
});

it('filter: attributes_count inRange/lessThan/notEqual number conditions', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $none = ProductCategory::factory()->create(['name' => 'None']);
    $one = ProductCategory::factory()->create(['name' => 'One']);
    $one->attributes()->attach(Attribute::factory()->create()->id, ['is_required' => false, 'sort_order' => 0]);
    Sanctum::actingAs($actor);

    $inRange = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['attributes_count' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 1, 'filterTo' => 5]],
    ])->assertOk();
    expect(collect($inRange->json('items'))->pluck('name')->all())->toBe(['One']);

    $lessThan = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['attributes_count' => ['filterType' => 'number', 'type' => 'lessThan', 'filter' => 1]],
    ])->assertOk();
    expect(collect($lessThan->json('items'))->pluck('name')->all())->toBe(['None']);

    $notEqual = $this->postJson('/api/tables/product-categories/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['attributes_count' => ['filterType' => 'number', 'type' => 'notEqual', 'filter' => 0]],
    ])->assertOk();
    expect(collect($notEqual->json('items'))->pluck('name')->all())->toBe(['One']);
});

// ---------------------------------------------------------------------------
// bulk-delete respects the restrictive-delete guard (newly reachable domain)
// ---------------------------------------------------------------------------

it('bulk-delete: a category with products is guarded, not force-deleted', function () {
    $actor = productCategoryUserWith(['viewAny', 'delete']);
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/product-categories/bulk-delete', ['ids' => [$category->id]])->assertOk();

    $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
});
