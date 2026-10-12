<?php

use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function restrictPurchaseOrderField(object $user, string $field): void
{
    $role = Role::create(['name' => "reader-{$field}"]);
    $user->assignRole($role);
    RoleFieldPermission::query()->create([
        'role_id' => $role->id, 'resource' => 'purchase-orders', 'field' => $field,
        'visible' => true, 'editable' => false, 'required' => false,
    ]);
}

it('AC-017: a role with payment_method_id read-only cannot change it, and the detail says so', function () {
    $user = purchaseOrderUserWith(['view', 'update']);
    restrictPurchaseOrderField($user, 'payment_method_id');
    Sanctum::actingAs($user);
    $method = PaymentMethod::factory()->create();
    $order = purchaseOrderWithLines(attributes: ['payment_method_id' => $method->id]);

    $this->getJson("/api/purchase-orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.field_permissions.payment_method_id.editable', false)
        ->assertJsonPath('data.field_permissions.payment_method_id.visible', true)
        ->assertJsonPath('data.field_permissions.description.editable', true);

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['payment_method_id' => PaymentMethod::factory()->create()->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_method_id');
    expect($order->fresh()->payment_method_id)->toBe($method->id);

    // Resubmitting the stored value is not a change.
    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['description' => 'Still editable']))
        ->assertOk()
        ->assertJsonPath('data.description', 'Still editable')
        ->assertJsonPath('data.payment_method.id', $method->id);
});

it('AC-017: the create-context metadata carries the restriction', function () {
    $user = purchaseOrderUserWith(['view', 'create']);
    restrictPurchaseOrderField($user, 'payment_method_id');
    Sanctum::actingAs($user);

    $this->getJson('/api/meta/purchase-orders')
        ->assertOk()
        ->assertJsonPath('permissions.fields.payment_method_id.editable', false)
        ->assertJsonPath('permissions.fields.supplier_id.editable', true)
        ->assertJsonPath('permissions.fields.supplier_id.required', true);
});

it('AC-017: a read-only sector_ids compares against the stored sectors', function () {
    $user = purchaseOrderUserWith(['view', 'update']);
    restrictPurchaseOrderField($user, 'sector_ids');
    Sanctum::actingAs($user);
    $order = purchaseOrderWithLines();
    $sector = Sector::factory()->create();
    $order->sectors()->sync([$sector->id]);

    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['sector_ids' => [$sector->id]]))->assertOk();
    $this->putJson("/api/purchase-orders/{$order->id}", purchaseOrderUpdatePayload($order, ['sector_ids' => [Sector::factory()->create()->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sector_ids');
});

it('meta/purchase-orders and meta/cost-types answer to a user with view, 403 without', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view']));
    $this->getJson('/api/meta/purchase-orders')->assertOk()->assertJsonPath('data.fields.0.key', 'supplier_id');

    Sanctum::actingAs(costTypeUserWith(['view']));
    $this->getJson('/api/meta/cost-types')->assertOk();

    Sanctum::actingAs(purchaseOrderUserWith(['create']));
    $this->getJson('/api/meta/purchase-orders')->assertForbidden();
});

it('AC-018: registries/for-select exposes meta.is_qualified_supplier and keeps the other meta keys', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('registries.viewAny', 'web'));
    Sanctum::actingAs($user);
    Registry::factory()->create(['name' => 'Zeta Qualified', 'is_supplier' => true, 'is_qualified_supplier' => true]);
    Registry::factory()->create(['name' => 'Alpha Plain', 'is_supplier' => true, 'is_qualified_supplier' => false]);

    $items = collect($this->getJson('/api/registries/for-select?is_supplier=1')->assertOk()->json('items'))->keyBy('label');

    expect($items['Zeta Qualified']['meta']['is_qualified_supplier'])->toBeTrue()
        ->and($items['Alpha Plain']['meta']['is_qualified_supplier'])->toBeFalse()
        ->and($items['Alpha Plain']['meta'])->toHaveKeys(['commercial', 'reporter', 'supervisor', 'managers']);
});
