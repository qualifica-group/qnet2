<?php

use App\Models\ProductTypology;
use App\Models\User;
use Database\Seeders\ProductTypologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Supplier commission switch of a typology (spec 0202; REQUIREMENT CHANGED by spec 0306: the direction moved to the
 * commission rule, the typology keeps only the switch).
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

it('AC-005: a new typology is born disabled, the switch is stored and the direction is no longer part of the resource', function () {
    Sanctum::actingAs(supplierDirectionActor());

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'gray'])
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_enabled', false)
        ->assertJsonMissingPath('data.supplier_commission_direction');

    // Spec 0306 R-3: a stale client still sending the direction is ignored, not rejected.
    $this->postJson('/api/product-typologies', ['name' => 'Ricevuta', 'code' => 'received', 'color' => 'gray', 'supplier_commission_enabled' => true, 'supplier_commission_direction' => 'RECEIVED'])
        ->assertCreated()
        ->assertJsonPath('data.supplier_commission_enabled', true)
        ->assertJsonMissingPath('data.supplier_commission_direction');

    expect(Schema::hasColumn('product_typologies', 'supplier_commission_direction'))->toBeFalse();
});

it('AC-005: update toggles the switch; show and table expose the switch but not the direction', function () {
    Sanctum::actingAs(supplierDirectionActor());
    $typology = ProductTypology::factory()->supplierCommission()->create();

    $this->getJson("/api/product-typologies/{$typology->id}")
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_enabled', true)
        ->assertJsonMissingPath('data.supplier_commission_direction');

    $this->patchJson("/api/product-typologies/{$typology->id}", ['supplier_commission_enabled' => false, 'supplier_commission_direction' => 'PAID'])
        ->assertOk()
        ->assertJsonPath('data.supplier_commission_enabled', false)
        ->assertJsonMissingPath('data.supplier_commission_direction');

    $columns = collect($this->getJson('/api/tables/product-typologies/columns')->assertOk()->json('data.columns'))->pluck('id');
    expect($columns)->toContain('supplier_commission_enabled')->not->toContain('supplier_commission_direction');

    $row = $this->postJson('/api/tables/product-typologies/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row)->toHaveKey('supplier_commission_enabled')->not->toHaveKey('supplier_commission_direction');
});

it('AC-005: a role without update permission cannot change the Supplier commission fields', function () {
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

it('AC-005: the seeder creates institution and consultancy with the switch on, idempotently', function () {
    $this->seed(ProductTypologySeeder::class);
    $this->seed(ProductTypologySeeder::class);

    expect(ProductTypology::where('code', 'consultancy')->sole()->supplier_commission_enabled)->toBeTrue()
        ->and(ProductTypology::where('code', 'institution')->sole()->supplier_commission_enabled)->toBeTrue();
});
