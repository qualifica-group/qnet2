<?php

use App\Models\CommissionSettlement;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function supplierLinkUser(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("referents.{$ability}");
    }

    return $user;
}

function restrictSupplierField(User $user, bool $visible, bool $editable): void
{
    $role = Role::create(['name' => 'restricted-supplier-'.uniqid()]);
    $user->assignRole($role);
    RoleFieldPermission::query()->create([
        'role_id' => $role->id, 'resource' => 'referents', 'field' => 'supplier_registry_id',
        'visible' => $visible, 'editable' => $editable, 'required' => false,
    ]);
}

it('AC-014: saves supplier_registry_id on update and returns it with the supplier {id,name}', function () {
    Sanctum::actingAs(supplierLinkUser(['view', 'update']));
    $referent = Referent::factory()->create();
    $supplier = Registry::factory()->supplier()->create(['name' => 'Fornitore Spa']);

    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $supplier->id])
        ->assertOk()
        ->assertJsonPath('data.supplier_registry_id', $supplier->id)
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.supplier.name', 'Fornitore Spa');

    $this->getJson("/api/referents/{$referent->id}")
        ->assertOk()
        ->assertJsonPath('data.supplier.name', 'Fornitore Spa');

    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => null])
        ->assertOk()
        ->assertJsonPath('data.supplier_registry_id', null)
        ->assertJsonPath('data.supplier', null);
});

it('AC-014: a registry that is not a supplier is a 422', function () {
    Sanctum::actingAs(supplierLinkUser(['view', 'update']));
    $referent = Referent::factory()->create();
    $customer = Registry::factory()->create(['is_supplier' => false]);

    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $customer->id])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_registry_id');
    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => 999999])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_registry_id');

    expect($referent->fresh()->supplier_registry_id)->toBeNull();
});

it('AC-014: changing the supplier with an APPROVED settlement is a 422, the same value and other statuses pass', function () {
    Sanctum::actingAs(supplierLinkUser(['view', 'update']));
    $current = Registry::factory()->supplier()->create();
    $other = Registry::factory()->supplier()->create();
    $referent = Referent::factory()->create(['supplier_registry_id' => $current->id]);
    CommissionSettlement::factory()->issued()->create(['referent_id' => $referent->id]);

    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $other->id])->assertOk();
    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $current->id])->assertOk();

    CommissionSettlement::factory()->approved()->create(['referent_id' => $referent->id]);

    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $current->id])
        ->assertOk();
    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $other->id])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_registry_id');
    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_registry_id');

    expect($referent->fresh()->supplier_registry_id)->toBe($current->id);
});

it('AC-014: a read-only supplier field rejects a change; a hidden one is dropped from the detail', function () {
    $user = supplierLinkUser(['view', 'update']);
    restrictSupplierField($user, visible: true, editable: false);
    $hiddenUser = supplierLinkUser(['view']);
    restrictSupplierField($hiddenUser, visible: false, editable: false);
    Sanctum::actingAs($user);
    $referent = Referent::factory()->create();
    $supplier = Registry::factory()->supplier()->create();

    $this->getJson("/api/referents/{$referent->id}")
        ->assertJsonPath('permissions.fields.supplier_registry_id.editable', false);
    $this->patchJson("/api/referents/{$referent->id}", ['supplier_registry_id' => $supplier->id])
        ->assertUnprocessable()->assertJsonValidationErrors('supplier_registry_id');

    Sanctum::actingAs($hiddenUser);

    $data = $this->getJson("/api/referents/{$referent->id}")->assertOk()->json('data');
    expect($data)->not->toHaveKeys(['supplier_registry_id', 'supplier']);
});
