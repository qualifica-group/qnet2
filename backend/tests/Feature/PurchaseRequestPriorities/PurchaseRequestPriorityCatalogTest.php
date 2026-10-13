<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// spec 0247, AC-002: CRUD, permissions, default, reorder and the delete guard of the RDA priority catalogue.

function purchaseRequestPriorityActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        Permission::findOrCreate("purchase-request-priorities.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("purchase-request-priorities.{$ability}");
    }

    return $user;
}

it('AC-002: the migration leaves the five reference priorities, Media being the default', function () {
    expect(PurchaseRequestPriority::query()->orderBy('sort_order')->pluck('name')->all())->toBe(['Bassa', 'Media', 'Alta', 'Urgente', 'Critica'])
        ->and(PurchaseRequestPriority::query()->where('is_default', true)->pluck('name')->all())->toBe(['Media']);
});

it('AC-002: creates a priority at the end of the order with the contract shape', function () {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['create']));

    $response = $this->postJson('/api/purchase-request-priorities', ['name' => 'Differibile', 'color' => 'teal', 'icon' => 'flag'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Differibile')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_default', false)
        ->assertJsonStructure(['permissions', 'data' => ['id', 'name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'is_default']]);

    expect($response->json('data.sort_order'))->toBeGreaterThan(PurchaseRequestPriority::query()->where('name', 'Critica')->value('sort_order'));
});

it('AC-002: validates the payload and refuses a client-written sort_order', function (array $payload, string $key) {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['create']));

    $this->postJson('/api/purchase-request-priorities', $payload)->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    'duplicate name' => [['name' => 'Media', 'color' => 'blue'], 'name'],
    'unknown color' => [['name' => 'X', 'color' => '#ff0000'], 'color'],
    'unknown icon' => [['name' => 'X', 'color' => 'blue', 'icon' => 'nope'], 'icon'],
    'sort_order' => [['name' => 'X', 'color' => 'blue', 'sort_order' => 3], 'sort_order'],
]);

it('AC-002: updates a priority and forbids it without the update permission', function () {
    $priority = PurchaseRequestPriority::factory()->create(['name' => 'Old']);

    Sanctum::actingAs(purchaseRequestPriorityActorWith(['view']));
    $this->putJson("/api/purchase-request-priorities/{$priority->id}", ['name' => 'New'])->assertForbidden();

    Sanctum::actingAs(purchaseRequestPriorityActorWith(['update']));
    $this->putJson("/api/purchase-request-priorities/{$priority->id}", ['name' => 'New'])->assertOk()->assertJsonPath('data.name', 'New');
});

it('AC-002: the endpoints are forbidden without the matching permission', function () {
    $priority = PurchaseRequestPriority::factory()->create();
    Sanctum::actingAs(purchaseRequestPriorityActorWith([]));

    $this->getJson("/api/purchase-request-priorities/{$priority->id}")->assertForbidden();
    $this->postJson('/api/purchase-request-priorities', ['name' => 'X', 'color' => 'blue'])->assertForbidden();
    $this->deleteJson("/api/purchase-request-priorities/{$priority->id}")->assertForbidden();
    $this->postJson('/api/purchase-request-priorities/reorder', ['ordered_ids' => [$priority->id]])->assertForbidden();
});

it('AC-002: setting a default clears the previous one and an inactive row cannot be the default', function () {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['update']));
    $alta = PurchaseRequestPriority::query()->where('name', 'Alta')->firstOrFail();

    $this->putJson("/api/purchase-request-priorities/{$alta->id}", ['is_default' => true])->assertOk()->assertJsonPath('data.is_default', true);

    expect(PurchaseRequestPriority::query()->where('is_default', true)->pluck('name')->all())->toBe(['Alta']);

    $this->putJson("/api/purchase-request-priorities/{$alta->id}", ['is_active' => false])->assertUnprocessable();
    expect($alta->fresh()->is_active)->toBeTrue();
});

it('AC-002: delete answers 409 for a priority used by a request, 204 for a free one', function () {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['delete']));
    $used = PurchaseRequestPriority::factory()->create();
    $free = PurchaseRequestPriority::factory()->create();
    PurchaseRequest::factory()->create(['purchase_request_priority_id' => $used->id]);

    $this->deleteJson("/api/purchase-request-priorities/{$used->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('purchase_request_priorities.in_use'));
    $this->deleteJson("/api/purchase-request-priorities/{$free->id}")->assertNoContent();

    expect($used->fresh())->not->toBeNull()->and($free->fresh())->toBeNull();
});

it('AC-002: reorder resequences every row and is reflected in the grid and in for-select', function () {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['update', 'viewAny', 'view']));
    $ids = PurchaseRequestPriority::query()->orderBy('sort_order')->pluck('id')->reverse()->values()->all();

    $response = $this->postJson('/api/purchase-request-priorities/reorder', ['ordered_ids' => $ids])->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($ids)
        ->and(PurchaseRequestPriority::query()->orderBy('sort_order')->pluck('id')->all())->toBe($ids)
        ->and(collect($this->getJson('/api/purchase-request-priorities/for-select')->assertOk()->json('items'))->pluck('id')->all())->toBe($ids);

    $this->postJson('/api/purchase-request-priorities/reorder', ['ordered_ids' => array_slice($ids, 1)])->assertUnprocessable();
});

it('AC-002: for-select lists only active rows, hydrates ids and carries badge and default meta', function () {
    Sanctum::actingAs(User::factory()->create());
    $inactive = PurchaseRequestPriority::factory()->create(['name' => 'Spenta', 'is_active' => false]);

    $items = collect($this->getJson('/api/purchase-request-priorities/for-select')->assertOk()->json('items'));

    expect($items->pluck('label')->all())->toBe(['Bassa', 'Media', 'Alta', 'Urgente', 'Critica'])
        ->and($items->firstWhere('label', 'Media')['meta'])->toMatchArray(['color' => 'blue', 'icon' => 'gauge', 'is_default' => true]);

    $hydrated = collect($this->getJson('/api/purchase-request-priorities/for-select?ids[]='.$inactive->id)->assertOk()->json('items'));
    expect($hydrated->pluck('label')->all())->toContain('Spenta');
});

it('AC-002: the catalogue grid lists the rows by sort_order for a user who may view them', function () {
    Sanctum::actingAs(purchaseRequestPriorityActorWith(['viewAny', 'view']));

    $names = collect($this->postJson('/api/tables/purchase-request-priorities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))->pluck('name')->all();

    expect($names)->toBe(['Bassa', 'Media', 'Alta', 'Urgente', 'Critica']);

    Sanctum::actingAs(purchaseRequestPriorityActorWith([]));
    $this->postJson('/api/tables/purchase-request-priorities/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});
