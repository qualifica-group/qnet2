<?php

use App\Enums\StockMovementType as Type;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('0307 AC-010: reads need stock.viewAny, with only viewAny the grid rows are not manageable', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    stockSeed($product, $site, '3.000');

    Sanctum::actingAs(stockUserWith([]));
    $this->getJson("/api/stock-balances?product_id={$product->id}")->assertForbidden();
    $this->getJson("/api/stock-movements?product_id={$product->id}&operational_site_id={$site->id}")->assertForbidden();
    $this->getJson('/api/tables/stock-balances/columns')->assertForbidden();
    $this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->getJson('/api/tables/stock-movements/columns')->assertForbidden();

    Sanctum::actingAs(stockUserWith(['viewAny']));
    $this->getJson("/api/stock-balances?product_id={$product->id}")->assertOk();
    $this->getJson('/api/tables/stock-movements/columns')->assertOk();
    $rows = $this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect($rows)->toHaveCount(1)->and($rows[0]['abilities'])->toBe(['manage' => false]);
});

it('0307 AC-010: manage alone also opens the reads and marks the rows manageable', function () {
    $product = stockTrackedProduct();
    stockSeed($product, OperationalSite::factory()->create(), '3.000');

    Sanctum::actingAs(stockUserWith(['manage']));
    $this->getJson("/api/stock-balances?product_id={$product->id}")->assertOk();
    $rows = $this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect($rows[0]['abilities'])->toBe(['manage' => true]);
});

it('0307 AC-011: balances by user or product, in site / free-first order, with the contract shape', function () {
    $product = stockTrackedProduct();
    $other = stockTrackedProduct();
    [$a, $b] = [OperationalSite::factory()->create(['alias' => 'Alpha']), OperationalSite::factory()->create(['alias' => 'Beta'])];
    $user = User::factory()->create(['name' => 'Anna']);
    stockSeed($product, $b, '2.000');
    stockSeed($product, $a, '1.000', $user);
    stockSeed($product, $a, '5.500');
    stockSeed($other, $a, '9.000', $user);
    Sanctum::actingAs(stockUserWith(['viewAny']));

    $byProduct = $this->getJson("/api/stock-balances?product_id={$product->id}")->assertOk()->json('data');

    expect(array_map(fn ($row) => [$row['operational_site']['label'], $row['user']['name'] ?? null], $byProduct))
        ->toBe([['Alpha', null], ['Alpha', 'Anna'], ['Beta', null]])
        ->and($byProduct[0])->toMatchArray(['quantity' => '5.500', 'last_moved_on' => now()->toDateString()])
        ->and($byProduct[0]['product'])->toBe([
            'id' => $product->id, 'code' => $product->code, 'name' => $product->name,
            'unit_of_measure' => ['id' => $product->unitOfMeasure->id, 'code' => $product->unitOfMeasure->code, 'name' => $product->unitOfMeasure->name],
        ])
        ->and($byProduct[0]['operational_site'])->toBe(['id' => $a->id, 'label' => 'Alpha', 'is_active' => true])
        ->and($byProduct[1]['user'])->toBe(['id' => $user->id, 'name' => 'Anna', 'is_active' => true]);

    $byUser = $this->getJson("/api/stock-balances?user_id={$user->id}")->assertOk()->json('data');
    expect(collect($byUser)->pluck('product.id')->sort()->values()->all())->toBe(collect([$product->id, $other->id])->sort()->values()->all())
        ->and(collect($byUser)->pluck('user.id')->unique()->all())->toBe([$user->id]);

    $this->getJson('/api/stock-balances')->assertUnprocessable();
});

it('0307 AC-011: the history of a row lists the movements where the pair is source or destination, newest first', function () {
    $product = stockTrackedProduct();
    [$a, $b] = [OperationalSite::factory()->create(), OperationalSite::factory()->create()];
    $user = User::factory()->create();
    stockSeed($product, $a, '10.000');
    Sanctum::actingAs(stockUserWith());
    $post = fn (array $payload) => $this->postJson('/api/stock-movements', $payload)->assertCreated();

    $post(stockPayload(Type::Load, $product, $a, ['quantity' => 4, 'moved_on' => '2026-01-01']));
    $post(stockPayload(Type::Assign, $product, $a, ['user_id' => $user->id, 'quantity' => 1, 'moved_on' => '2026-01-03']));
    $post(stockPayload(Type::Transfer, $product, $a, ['to_operational_site_id' => $b->id, 'quantity' => 2, 'moved_on' => '2026-01-02']));
    $post(stockPayload(Type::Load, $product, $b, ['quantity' => 7, 'moved_on' => '2026-01-04']));

    $free = $this->getJson("/api/stock-movements?product_id={$product->id}&operational_site_id={$a->id}")->assertOk()->json('data');
    expect(array_column($free, 'type'))->toBe(['ASSIGN', 'TRANSFER', 'LOAD'])
        ->and($free[0])->toHaveKeys(['id', 'type', 'product', 'quantity', 'from_operational_site', 'from_user', 'to_operational_site', 'to_user', 'moved_on', 'note', 'created_by', 'created_at']);

    $assigned = $this->getJson("/api/stock-movements?product_id={$product->id}&operational_site_id={$a->id}&user_id={$user->id}")->assertOk()->json('data');
    expect(array_column($assigned, 'type'))->toBe(['ASSIGN']);

    $this->getJson("/api/stock-movements?product_id={$product->id}")->assertUnprocessable();
});

it('0307 AC-010: the grids list only rows above zero and movements newest first, filtered and sorted from declared columns', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create(['alias' => 'Alpha']);
    $user = User::factory()->create(['name' => 'Anna']);
    stockSeed($product, $site, '2.000');
    stockSeed($product, $site, '1.000', $user);
    StockMovement::factory()->create(['product_id' => $product->id, 'to_operational_site_id' => $site->id, 'moved_on' => '2026-01-01']);
    StockMovement::factory()->create(['product_id' => $product->id, 'to_operational_site_id' => $site->id, 'moved_on' => '2026-02-01', 'type' => Type::Assign]);
    Sanctum::actingAs(stockUserWith(['viewAny']));

    $columns = $this->getJson('/api/tables/stock-balances/columns')->assertOk()->json('data');
    expect(collect($columns['columns'])->pluck('id')->all())->toContain('product_code', 'holder', 'quantity', 'last_moved_on')
        ->and(collect($columns['columns'])->firstWhere('id', 'holder')['enumKey'])->toBe('stock_holder')
        ->and(collect($columns['columns'])->firstWhere('id', 'quantity')['label'])->toBe('stock.balances.columns.quantity');

    $assigned = $this->postJson('/api/tables/stock-balances/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['holder' => ['filterType' => 'set', 'values' => ['ASSIGNED']]],
    ])->assertOk()->json('items');
    expect($assigned)->toHaveCount(1)->and($assigned[0])->toMatchArray(['user_name' => 'Anna', 'holder' => 'ASSIGNED', 'operational_site' => 'Alpha']);

    $bySite = $this->postJson('/api/tables/stock-balances/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['operational_site' => ['filterType' => 'set', 'values' => ['Alpha']]],
        'sortModel' => [['colId' => 'product_name', 'sort' => 'asc']],
    ])->assertOk()->json('items');
    expect($bySite)->toHaveCount(2);

    $movements = $this->postJson('/api/tables/stock-movements/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
    expect(array_column($movements, 'moved_on'))->toBe(['2026-02-01', '2026-01-01'])
        ->and($movements[0]['to_site'])->toBe('Alpha');
});

it('0307 AC-010: the site value list, the name search and the code sort of the stock grid', function () {
    [$first, $second] = [Product::factory()->internalUse()->create(['name' => 'Notebook', 'code' => 'PRD-2']), Product::factory()->internalUse()->create(['name' => 'Chair', 'code' => 'PRD-1'])];
    $site = OperationalSite::factory()->create(['alias' => 'Alpha']);
    stockSeed($first, $site, '1.000');
    stockSeed($second, OperationalSite::factory()->create(['alias' => 'Beta']), '1.000');
    Sanctum::actingAs(stockUserWith(['viewAny']));

    $values = $this->postJson('/api/tables/stock-balances/values', ['columnId' => 'operational_site'])->assertOk()->json('data.values');
    expect($values)->toBe(['Alpha', 'Beta']);

    $search = $this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'Note'])->assertOk()->json('items');
    expect(array_column($search, 'product_name'))->toBe(['Notebook']);

    $sorted = $this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'product_code', 'sort' => 'asc']]])->assertOk()->json('items');
    expect(array_column($sorted, 'product_code'))->toBe(['PRD-1', 'PRD-2']);
});

it('0307 BE-4: the row actions follow free vs assigned rows and the manage permission', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    $user = User::factory()->create();
    $free = stockSeed($product, $site, '2.000');
    $assigned = stockSeed($product, $site, '1.000', $user);
    $actionsOf = fn () => collect($this->postJson('/api/tables/stock-balances/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->mapWithKeys(fn ($row) => [$row['id'] => $row['actions']])->all();

    Sanctum::actingAs(stockUserWith());
    $rows = $actionsOf();
    expect($rows[$free->id])->toEqualCanonicalizing(['assign', 'transfer', 'unload', 'adjust', 'history'])
        ->and($rows[$assigned->id])->toEqualCanonicalizing(['return', 'transfer', 'unload', 'adjust', 'history']);

    $catalogue = collect($this->getJson('/api/tables/stock-balances/columns')->json('data.actions'))->keyBy('key');
    expect($catalogue->keys()->all())->toEqualCanonicalizing(['assign', 'return', 'transfer', 'unload', 'adjust', 'history'])
        ->and($catalogue['assign']['icon'])->toBe('user-plus')
        ->and($catalogue['history']['label'])->toBe('stock.actions.history');

    Sanctum::actingAs(stockUserWith(['viewAny']));
    expect($actionsOf())->toBe([$free->id => ['history'], $assigned->id => ['history']]);
    expect(collect($this->getJson('/api/tables/stock-balances/columns')->json('data.actions'))->pluck('key')->all())->toBe(['history']);
});
