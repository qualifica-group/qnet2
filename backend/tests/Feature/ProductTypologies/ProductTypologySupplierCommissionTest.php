<?php

use App\Enums\SupplierCommissionDirection;
use App\Models\ProductTypology;
use App\Models\User;
use Database\Seeders\ProductTypologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Supplier commission settings of a typology (spec 0202, AC-001/AC-002).
 */
uses(RefreshDatabase::class);

function supplierDirectionActor(): User
{
    $user = User::factory()->create();

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("product-typologies.{$ability}");
        $user->givePermissionTo("product-typologies.{$ability}");
    }

    return $user;
}

it('AC-001: enabled without a direction is a 422', function () {
    Sanctum::actingAs(supplierDirectionActor());

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'gray', 'supplier_commission_enabled' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_commission_direction');

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'gray', 'supplier_commission_enabled' => true, 'supplier_commission_direction' => 'BOTH'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_commission_direction');
});

it('AC-001: a new typology is born disabled and a direction is stored only with the switch on', function () {
    Sanctum::actingAs(supplierDirectionActor());

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'gray'])
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_enabled', false)
        ->assertJsonPath('data.supplier_commission_direction', null);

    $this->postJson('/api/product-typologies', ['name' => 'Altro', 'code' => 'other', 'color' => 'gray', 'supplier_commission_enabled' => false, 'supplier_commission_direction' => 'PAID'])
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_enabled', false)
        ->assertJsonPath('data.supplier_commission_direction', null);

    $this->postJson('/api/product-typologies', ['name' => 'Ricevuta', 'code' => 'received', 'color' => 'gray', 'supplier_commission_enabled' => true, 'supplier_commission_direction' => 'RECEIVED'])
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_enabled', true)
        ->assertJsonPath('data.supplier_commission_direction', 'RECEIVED');
});

it('AC-001: update toggles the switch and switching off clears the direction; show and table expose both fields', function () {
    Sanctum::actingAs(supplierDirectionActor());
    $typology = ProductTypology::factory()->supplierCommission(SupplierCommissionDirection::Paid)->create();

    $this->getJson("/api/product-typologies/{$typology->id}")
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_enabled', true)
        ->assertJsonPath('data.supplier_commission_direction', 'PAID');

    $this->patchJson("/api/product-typologies/{$typology->id}", ['supplier_commission_enabled' => true, 'supplier_commission_direction' => 'RECEIVED'])
        ->assertOk()->assertJsonPath('data.supplier_commission_direction', 'RECEIVED');

    $this->patchJson("/api/product-typologies/{$typology->id}", ['supplier_commission_enabled' => true, 'supplier_commission_direction' => null])
        ->assertUnprocessable();

    $this->patchJson("/api/product-typologies/{$typology->id}", ['supplier_commission_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_enabled', false)
        ->assertJsonPath('data.supplier_commission_direction', null);

    $columns = collect($this->getJson('/api/tables/product-typologies/columns')->assertOk()->json('data.columns'))->pluck('id');
    expect($columns)->toContain('supplier_commission_enabled', 'supplier_commission_direction');

    $row = $this->postJson('/api/tables/product-typologies/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row)->toHaveKeys(['supplier_commission_enabled', 'supplier_commission_direction']);
});

it('AC-001: a role without update permission cannot change the Supplier commission fields', function () {
    $user = User::factory()->create();
    foreach (['viewAny', 'view'] as $ability) {
        Permission::findOrCreate("product-typologies.{$ability}");
        $user->givePermissionTo("product-typologies.{$ability}");
    }
    Sanctum::actingAs($user);
    $typology = ProductTypology::factory()->create();

    $this->patchJson("/api/product-typologies/{$typology->id}", ['supplier_commission_enabled' => true, 'supplier_commission_direction' => 'PAID'])
        ->assertForbidden();
});

it('AC-002: the seeder creates institution as RECEIVED and consultancy as PAID, idempotently', function () {
    $this->seed(ProductTypologySeeder::class);
    $this->seed(ProductTypologySeeder::class);

    expect(ProductTypology::where('code', 'consultancy')->sole()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Paid)
        ->and(ProductTypology::where('code', 'institution')->sole()->supplier_commission_direction)->toBe(SupplierCommissionDirection::Received)
        ->and(ProductTypology::where('code', 'institution')->sole()->supplier_commission_enabled)->toBeTrue();
});
