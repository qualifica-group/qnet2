<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrderStatusLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// spec 0248: presentation catalogue of the ODA statuses (AC-002, AC-003, AC-004).

function purchaseOrderStatusActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'update', 'export', 'viewActivity'] as $ability) {
        Permission::findOrCreate("purchase-order-statuses.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("purchase-order-statuses.{$ability}");
    }

    return $user;
}

function purchaseOrderStatusRow(string $systemKey): PurchaseOrderStatusLabel
{
    return PurchaseOrderStatusLabel::query()->where('system_key', $systemKey)->firstOrFail();
}

it('AC-002: updates name, color and icon with the update permission and returns the contract shape', function () {
    Sanctum::actingAs(purchaseOrderStatusActorWith(['update']));
    $row = purchaseOrderStatusRow('draft');

    $this->patchJson("/api/purchase-order-statuses/{$row->id}", ['name' => 'In bozza', 'color' => 'teal', 'icon' => 'flag'])
        ->assertOk()
        ->assertJsonPath('data.name', 'In bozza')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.icon', 'flag')
        ->assertJsonPath('data.system_key', 'draft')
        ->assertJsonStructure(['permissions', 'data' => ['id', 'system_key', 'name', 'color', 'icon', 'sort_order']]);

    $this->putJson("/api/purchase-order-statuses/{$row->id}", ['icon' => null])->assertOk()->assertJsonPath('data.icon', null);

    expect($row->fresh()->name)->toBe('In bozza');
});

it('AC-002: show needs the view permission and update the update permission', function () {
    $row = purchaseOrderStatusRow('approved');

    Sanctum::actingAs(purchaseOrderStatusActorWith([]));
    $this->getJson("/api/purchase-order-statuses/{$row->id}")->assertForbidden();

    Sanctum::actingAs(purchaseOrderStatusActorWith(['view']));
    $this->getJson("/api/purchase-order-statuses/{$row->id}")->assertOk()->assertJsonPath('data.system_key', 'approved');
    $this->patchJson("/api/purchase-order-statuses/{$row->id}", ['name' => 'X'])->assertForbidden();

    expect($row->fresh()->name)->toBe('Approvato');
});

it('AC-002: validates the payload and refuses system_key and sort_order', function (array $payload, string $key) {
    Sanctum::actingAs(purchaseOrderStatusActorWith(['update']));
    $row = purchaseOrderStatusRow('draft');

    $this->patchJson("/api/purchase-order-statuses/{$row->id}", $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    'duplicate name' => [['name' => 'Approvato'], 'name'],
    'empty name' => [['name' => ''], 'name'],
    'unknown color' => [['color' => '#ff0000'], 'color'],
    'unknown icon' => [['icon' => 'nope'], 'icon'],
    'system_key' => [['system_key' => 'other'], 'system_key'],
    'sort_order' => [['sort_order' => 3], 'sort_order'],
]);

it('AC-002: keeping the own name is not a duplicate', function () {
    Sanctum::actingAs(purchaseOrderStatusActorWith(['update']));
    $row = purchaseOrderStatusRow('draft');

    $this->patchJson("/api/purchase-order-statuses/{$row->id}", ['name' => 'Bozza', 'color' => 'gray'])->assertOk();
});

it('AC-003: there is no create or delete route and permissions:sync never creates those abilities', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $catalogue = $routes->filter(fn ($route) => str_starts_with($route->uri(), 'api/purchase-order-statuses') || str_starts_with($route->uri(), 'api/purchase-request-line-statuses'));

    expect($catalogue->isNotEmpty())->toBeTrue()
        ->and($catalogue->contains(fn ($route) => in_array('DELETE', $route->methods(), true)))->toBeFalse()
        ->and($catalogue->contains(fn ($route) => in_array('POST', $route->methods(), true) && ! str_ends_with($route->uri(), '/reorder')))->toBeFalse();

    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['purchase-order-statuses', 'purchase-request-line-statuses'] as $resource) {
        expect(Permission::where('name', "{$resource}.create")->exists())->toBeFalse()
            ->and(Permission::where('name', "{$resource}.delete")->exists())->toBeFalse()
            ->and(Permission::where('name', "{$resource}.update")->exists())->toBeTrue();
    }
});

it('AC-004: reorder with every row resequences and for-select follows the new order', function () {
    Sanctum::actingAs(purchaseOrderStatusActorWith(['update']));
    $ids = PurchaseOrderStatusLabel::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();

    $response = $this->postJson('/api/purchase-order-statuses/reorder', ['ordered_ids' => $ids])->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($ids)
        ->and(PurchaseOrderStatusLabel::query()->orderBy('sort_order')->pluck('id')->all())->toBe($ids)
        ->and(collect($this->getJson('/api/purchase-order-statuses/for-select')->assertOk()->json('items'))->pluck('id')->all())->toBe($ids);
});

it('AC-004: reorder with an incomplete, duplicated or foreign set answers 422 and without permission 403', function () {
    $ids = PurchaseOrderStatusLabel::query()->orderBy('sort_order')->pluck('id')->all();

    Sanctum::actingAs(purchaseOrderStatusActorWith(['view']));
    $this->postJson('/api/purchase-order-statuses/reorder', ['ordered_ids' => $ids])->assertForbidden();

    Sanctum::actingAs(purchaseOrderStatusActorWith(['update']));
    $this->postJson('/api/purchase-order-statuses/reorder', ['ordered_ids' => array_slice($ids, 1)])->assertUnprocessable();
    $this->postJson('/api/purchase-order-statuses/reorder', ['ordered_ids' => [...array_slice($ids, 1), 9999]])->assertUnprocessable();
    $this->postJson('/api/purchase-order-statuses/reorder', ['ordered_ids' => [...$ids, $ids[0]]])->assertUnprocessable();

    expect(PurchaseOrderStatusLabel::query()->orderBy('sort_order')->pluck('id')->all())->toBe($ids);
});

it('AC-002: for-select lists all seven rows by sort_order with the badge meta to any authenticated user', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/purchase-order-statuses/for-select')->assertOk();
    $items = $response->json('items');

    expect($items)->toHaveCount(7)
        ->and($items[0]['label'])->toBe('Bozza')
        ->and($items[0]['meta'])->toBe(['system_key' => 'draft', 'color' => 'slate', 'icon' => 'pen-line', 'sort_order' => 1])
        ->and(collect($items)->pluck('meta.system_key')->all())->toBe(PurchaseOrderStatus::values());

});

it('AC-002: for-select requires authentication', function () {
    $this->getJson('/api/purchase-order-statuses/for-select')->assertUnauthorized();
});

it('AC-002: the list table needs viewAny, is sorted by sort_order and offers no delete action', function () {
    Sanctum::actingAs(purchaseOrderStatusActorWith([]));
    $this->postJson('/api/tables/purchase-order-statuses/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();

    Sanctum::actingAs(purchaseOrderStatusActorWith(['viewAny', 'view', 'viewActivity']));
    $rows = $this->postJson('/api/tables/purchase-order-statuses/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items');
    $keys = collect($this->getJson('/api/tables/purchase-order-statuses/columns')->assertOk()->json('data.actions'))->pluck('key')->all();

    expect($rows)->toHaveCount(7)
        ->and($rows[0]['name'])->toBe('Bozza')
        ->and($rows[0]['actions'])->toBe(['view', 'activity'])
        ->and($keys)->toBe(['view', 'activity']);
});
