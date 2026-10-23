<?php

use App\Enums\ProductType;
use App\Enums\StockMovementType as Type;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function postMovement(array $payload)
{
    return test()->postJson('/api/stock-movements', $payload);
}

it('0307 AC-003: a LOAD creates the row and a second LOAD adds to the same row', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith());

    $response = postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 5]))->assertCreated();

    expect($response->json('data.movement.type'))->toBe('LOAD')
        ->and($response->json('data.movement.from_operational_site'))->toBeNull()
        ->and($response->json('data.movement.to_operational_site.id'))->toBe($site->id)
        ->and($response->json('data.movement.to_user'))->toBeNull()
        ->and($response->json('data.balances'))->toHaveCount(1)
        ->and($response->json('data.balances.0.quantity'))->toBe('5.000')
        ->and(stockQuantity($product, $site))->toBe('5.000');

    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => '2']))->assertCreated();

    expect(stockQuantity($product, $site))->toBe('7.000')
        ->and(StockBalance::count())->toBe(1)
        ->and(StockMovement::count())->toBe(2);
});

it('0307 AC-004: ASSIGN moves one piece to the user and rejects an inactive user', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    $user = User::factory()->create();
    stockSeed($product, $site, '7.000');
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Assign, $product, $site, ['user_id' => $user->id, 'quantity' => 1]))->assertCreated();

    expect(stockQuantity($product, $site))->toBe('6.000')
        ->and(stockQuantity($product, $site, $user))->toBe('1.000');

    $inactive = User::factory()->create(['is_active' => false]);
    postMovement(stockPayload(Type::Assign, $product, $site, ['user_id' => $inactive->id, 'quantity' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');

    expect(stockQuantity($product, $site))->toBe('6.000')->and(StockMovement::count())->toBe(1);
});

it('0307 AC-005: RETURN gives the piece back to the free row and deletes the assigned one', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    $user = User::factory()->create();
    stockSeed($product, $site, '6.000');
    stockSeed($product, $site, '1.000', $user);
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Return, $product, $site, ['user_id' => $user->id, 'quantity' => 1]))->assertCreated();

    expect(stockQuantity($product, $site, $user))->toBeNull()
        ->and(stockQuantity($product, $site))->toBe('7.000')
        ->and(StockMovement::where('type', 'RETURN')->count())->toBe(1);

    postMovement(stockPayload(Type::Return, $product, $site, ['quantity' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

it('0307 AC-006: TRANSFER moves free and assigned stock, never to the same or an inactive site', function () {
    $product = stockTrackedProduct();
    [$a, $b] = [OperationalSite::factory()->create(), OperationalSite::factory()->create()];
    $user = User::factory()->create();
    stockSeed($product, $a, '7.000');
    stockSeed($product, $a, '1.000', $user);
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Transfer, $product, $a, ['to_operational_site_id' => $b->id, 'quantity' => 3]))->assertCreated();
    postMovement(stockPayload(Type::Transfer, $product, $a, ['to_operational_site_id' => $b->id, 'user_id' => $user->id, 'quantity' => 1]))->assertCreated();

    expect(stockQuantity($product, $a))->toBe('4.000')
        ->and(stockQuantity($product, $b))->toBe('3.000')
        ->and(stockQuantity($product, $b, $user))->toBe('1.000')
        ->and(stockQuantity($product, $a, $user))->toBeNull();

    postMovement(stockPayload(Type::Transfer, $product, $a, ['to_operational_site_id' => $a->id, 'quantity' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('to_operational_site_id');

    $closed = OperationalSite::factory()->create(['is_active' => false]);
    postMovement(stockPayload(Type::Transfer, $product, $a, ['to_operational_site_id' => $closed->id, 'quantity' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('to_operational_site_id');

    expect(StockMovement::count())->toBe(2);
});

it('0307 AC-007: more than the stock is refused with the available quantity, nothing written', function (Type $type, array $extra) {
    $product = stockTrackedProduct();
    [$a, $b] = [OperationalSite::factory()->create(), OperationalSite::factory()->create()];
    $user = User::factory()->create();
    stockSeed($product, $a, '4.000');
    Sanctum::actingAs(stockUserWith());

    $extra = array_map(fn ($value) => $value === 'B' ? $b->id : ($value === 'U' ? $user->id : $value), $extra);
    $response = postMovement(stockPayload($type, $product, $a, [...$extra, 'quantity' => 5]))->assertUnprocessable();

    expect($response->json('errors.quantity.0'))->toContain('4.000')
        ->and(StockMovement::count())->toBe(0)
        ->and(stockQuantity($product, $a))->toBe('4.000');
})->with([
    'unload' => [Type::Unload, ['note' => 'broken']],
    'transfer' => [Type::Transfer, ['to_operational_site_id' => 'B']],
    'assign' => [Type::Assign, ['user_id' => 'U']],
]);

it('0307 AC-007: a source row that does not exist counts as zero available', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith());

    $response = postMovement(stockPayload(Type::Unload, $product, $site, ['quantity' => 1, 'note' => 'x']))->assertUnprocessable();

    expect($response->json('errors.quantity.0'))->toContain('0.000');
});

it('0307 AC-007: UNLOAD and ADJUSTMENT need a note', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    stockSeed($product, $site, '4.000');
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Unload, $product, $site, ['quantity' => 1]))->assertUnprocessable()->assertJsonValidationErrors('note');
    postMovement(stockPayload(Type::Adjustment, $product, $site, ['counted_quantity' => 2]))->assertUnprocessable()->assertJsonValidationErrors('note');
    postMovement(stockPayload(Type::Unload, $product, $site, ['quantity' => 1, 'note' => 'lost']))->assertCreated();

    expect(stockQuantity($product, $site))->toBe('3.000');
});

it('0307 AC-008: ADJUSTMENT records the difference and a zero count removes the row', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    stockSeed($product, $site, '4.000');
    Sanctum::actingAs(stockUserWith());

    $up = postMovement(stockPayload(Type::Adjustment, $product, $site, ['counted_quantity' => 6, 'note' => 'count']))->assertCreated();
    expect($up->json('data.movement.quantity'))->toBe('2.000')
        ->and($up->json('data.movement.from_operational_site'))->toBeNull()
        ->and($up->json('data.movement.to_operational_site.id'))->toBe($site->id)
        ->and(stockQuantity($product, $site))->toBe('6.000');

    $down = postMovement(stockPayload(Type::Adjustment, $product, $site, ['counted_quantity' => 1, 'note' => 'count']))->assertCreated();
    expect($down->json('data.movement.quantity'))->toBe('5.000')
        ->and($down->json('data.movement.to_operational_site'))->toBeNull()
        ->and($down->json('data.movement.from_operational_site.id'))->toBe($site->id)
        ->and(stockQuantity($product, $site))->toBe('1.000');

    postMovement(stockPayload(Type::Adjustment, $product, $site, ['counted_quantity' => 1, 'note' => 'count']))
        ->assertUnprocessable()->assertJsonValidationErrors('counted_quantity');

    $zero = postMovement(stockPayload(Type::Adjustment, $product, $site, ['counted_quantity' => 0, 'note' => 'gone']))->assertCreated();
    expect(stockQuantity($product, $site))->toBeNull()->and($zero->json('data.balances'))->toBe([]);
});

it('0307 AC-009: invalid movements are refused on the indicated field, with no write', function (array $overrides, string $field) {
    $site = OperationalSite::factory()->create();
    $closed = OperationalSite::factory()->create(['is_active' => false]);
    $user = User::factory()->create();
    $product = $overrides['untracked'] ?? false ? Product::factory()->create() : stockTrackedProduct();
    unset($overrides['untracked']);
    $target = ($overrides['closed_site'] ?? false) ? $closed : $site;
    unset($overrides['closed_site']);
    $overrides = array_map(fn ($value) => $value === 'U' ? $user->id : $value, $overrides);
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Load, $product, $target, ['quantity' => 1, ...$overrides]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(StockBalance::count())->toBe(0)->and(StockMovement::count())->toBe(0);
})->with([
    'untracked product' => [['untracked' => true], 'product_id'],
    'future date' => [['moved_on' => '2999-01-01'], 'moved_on'],
    'user on a load' => [['user_id' => 'U'], 'user_id'],
    'inactive site' => [['closed_site' => true], 'operational_site_id'],
    'zero quantity' => [['quantity' => 0], 'quantity'],
    'four decimals' => [['quantity' => '1.0001'], 'quantity'],
]);

it('0307 AC-009: a service product with INTERNAL cannot exist, a service product is not tracked', function () {
    $product = Product::factory()->create(['product_type' => ProductType::Service]);
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 1]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
});

it('0307 AC-010: recording needs stock.manage', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith(['viewAny']));

    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 1]))->assertForbidden();

    expect(StockMovement::count())->toBe(0);
});

it('0307 AC-014: the unique key rejects a second free row and two LOADs share one row', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith());

    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 1]))->assertCreated();
    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 1]))->assertCreated();

    expect(StockBalance::count())->toBe(1);
    expect(fn () => StockBalance::factory()->create(['product_id' => $product->id, 'operational_site_id' => $site->id, 'user_id' => null]))
        ->toThrow(QueryException::class);
});

it('0307 AC-014: the stock rows are written inside a transaction (the product lock is skipped by SQLite)', function () {
    $product = stockTrackedProduct();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs(stockUserWith());
    $writeLevels = [];
    DB::listen(function ($query) use (&$writeLevels) {
        if (str_starts_with($query->sql, 'insert into "stock_')) {
            $writeLevels[] = DB::transactionLevel();
        }
    });

    postMovement(stockPayload(Type::Load, $product, $site, ['quantity' => 1]))->assertCreated();

    // RefreshDatabase already wraps the test in one level: the service adds its own.
    expect($writeLevels)->toHaveCount(2)->and(min($writeLevels))->toBeGreaterThanOrEqual(2);
});
