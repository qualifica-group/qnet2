<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-018 — columns config
// ---------------------------------------------------------------------------

it('returns the 11 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = productUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/products/columns')->assertForbidden();

    $actor = productUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/products/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('products')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['code', 'name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'code', 'name', 'description', 'cost', 'price', 'category', 'state', 'product_type', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        // spec 0065, AC-009b: `code` is sortable, filterable and searchable.
        ->and($columns['code']['sortable'])->toBeTrue()
        ->and($columns['code']['filterable'])->toBeTrue()
        ->and($columns['code']['filterType'])->toBe('text')
        ->and($columns['description']['sortable'])->toBeFalse()
        ->and($columns['category']['filterType'])->toBe('set')
        ->and($columns['state']['filterType'])->toBe('set')
        ->and($columns['state']['sortable'])->toBeTrue()
        ->and($columns['product_type']['type'])->toBe('badge')
        ->and($columns['product_type']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-009b — `code` column: rows valorize it, sortable, filterable
// ---------------------------------------------------------------------------

it('rows expose the code column and values populate it (AC-009b)', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->create(['name' => 'Widget', 'code' => 'PRD-9001']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Widget');

    expect($row['code'])->toBe('PRD-9001');
});

it('sort: rows ordered by code, filter: text filter narrows by code (AC-009b)', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->create(['name' => 'Zeta', 'code' => 'PRD-0002']);
    Product::factory()->create(['name' => 'Alpha', 'code' => 'PRD-0001']);
    Sanctum::actingAs($actor);

    $sorted = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'code', 'sort' => 'asc']],
    ])->assertOk();
    expect(collect($sorted->json('items'))->pluck('code')->all())->toBe(['PRD-0001', 'PRD-0002']);

    $filtered = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'PRD-0002']],
    ])->assertOk();
    expect(collect($filtered->json('items'))->pluck('code')->all())->toBe(['PRD-0002']);
});

// ---------------------------------------------------------------------------
// AC-018 — rows shape (no N+1: category eager-loaded)
// ---------------------------------------------------------------------------

it('rows expose id/name/description/cost/price/category{id,name}/created_at + per-row actions', function () {
    $actor = productUserWith(['viewAny', 'view', 'update', 'delete']);
    $category = ProductCategory::factory()->create(['name' => 'Electronics']);
    Product::factory()->create(['name' => 'Gadget', 'category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Gadget');

    expect($row)->not->toBeNull()
        ->and($row['category'])->toBe(['id' => $category->id, 'name' => 'Electronics'])
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('rows expose product_type (defaulting to SERVICE) and its badge metadata', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->create(['name' => 'Consulting']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Consulting');
    expect($row['product_type'])->toBe('SERVICE');

    $columns = collect($this->getJson('/api/tables/products/columns')->json('data.columns'))->keyBy('id');
    $badges = collect($columns['product_type']['badges'] ?? [])->pluck('value')->all();
    expect($badges)->toContain('SERVICE')
        ->and($columns['product_type']['enumKey'] ?? null)->toBe('product_type');
});

it('values: product_type → distinct product types', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'product_type'])->assertOk();
    expect($response->json('data.values'))->toBe(['SERVICE']);
});

it('rows: no N+1 on the category relation', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->count(5)->create();
    Sanctum::actingAs($actor);

    Product::preventLazyLoading();

    $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    Product::preventLazyLoading(false);
});

// ---------------------------------------------------------------------------
// AC-018 — values endpoint (derived `category` set filter)
// ---------------------------------------------------------------------------

it('values: category → distinct category names, columnId outside the allow-list → 422', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['category_id' => $categoryA->id]);
    Product::factory()->create(['category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'category'])->assertOk();
    expect($response->json('data.values'))->toEqualCanonicalizing(['Electronics', 'Clothing']);

    $this->postJson('/api/tables/products/values', ['columnId' => 'not_a_column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('values: category search narrows the distinct list', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['category_id' => $categoryA->id]);
    Product::factory()->create(['category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'category', 'search' => 'Elec'])->assertOk();

    expect($response->json('data.values'))->toBe(['Electronics']);
});

it('sort: rows ordered by the derived category name', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Zebra Category']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Alpha Category']);
    Product::factory()->create(['name' => 'FromZebra', 'category_id' => $categoryA->id]);
    Product::factory()->create(['name' => 'FromAlpha', 'category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'category', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['FromAlpha', 'FromZebra']);
});

it('filter: category set filter narrows the rows via whereHas', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['name' => 'Laptop', 'category_id' => $categoryA->id]);
    Product::factory()->create(['name' => 'Shirt', 'category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['category' => ['filterType' => 'set', 'values' => ['Electronics']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Laptop']);
});

// ---------------------------------------------------------------------------
// derived `state` (Regione) column — geo reference data, localized to Italian
// ---------------------------------------------------------------------------

it('rows: state shows the Italian localized name, null when unset', function () {
    $actor = productUserWith(['viewAny']);
    $state = State::factory()->create(['name' => 'Lombardy']);
    Product::factory()->create(['name' => 'WithState', 'state_id' => $state->id]);
    Product::factory()->create(['name' => 'WithoutState']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $rows = collect($response->json('items'))->keyBy('name');

    expect($rows['WithState']['state'])->toBe(['id' => $state->id, 'name' => 'Lombardia'])
        ->and($rows['WithoutState']['state'])->toBeNull();
});

it('filter: state set filter matches the Italian display name against the English DB name', function () {
    $actor = productUserWith(['viewAny']);
    $lombardy = State::factory()->create(['name' => 'Lombardy']);
    $tuscany = State::factory()->create(['name' => 'Tuscany']);
    Product::factory()->create(['name' => 'InLombardy', 'state_id' => $lombardy->id]);
    Product::factory()->create(['name' => 'InTuscany', 'state_id' => $tuscany->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['state' => ['filterType' => 'set', 'values' => ['Lombardia']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('name')->all())->toBe(['InLombardy']);
});

it('sort: rows ordered by the derived state name', function () {
    $actor = productUserWith(['viewAny']);
    $stateA = State::factory()->create(['name' => 'Zebra State']);
    $stateB = State::factory()->create(['name' => 'Alpha State']);
    Product::factory()->create(['name' => 'FromZebraState', 'state_id' => $stateA->id]);
    Product::factory()->create(['name' => 'FromAlphaState', 'state_id' => $stateB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'state', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['FromAlphaState', 'FromZebraState']);
});

it('values: state → distinct Italian localized names', function () {
    $actor = productUserWith(['viewAny']);
    $state = State::factory()->create(['name' => 'Lombardy']);
    Product::factory()->create(['state_id' => $state->id]);
    Product::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'state'])->assertOk();
    expect($response->json('data.values'))->toBe(['Lombardia']);
});
