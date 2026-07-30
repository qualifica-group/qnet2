<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
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
// AC-019 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-019 — a non-editable GENERIC field changed by the DB matrix → 422
// ---------------------------------------------------------------------------

it('update: a generic field made non-editable by the DB matrix and changed → 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("products.{$ability}");
    }

    // `description` is the only non-mandatory generic field: cost/price/
    // product_type are mandatory (spec 0008) and therefore bypass the DB
    // matrix intersect, so the matrix can only lock a non-mandatory field.
    $role = Role::create(['name' => 'product-description-locked']);
    $role->givePermissionTo(['products.view', 'products.update']);
    $role->fieldPermissions()->create([
        'resource' => 'products',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $product = Product::factory()->create(['description' => 'locked']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['description' => 'changed'])
        ->assertStatus(422)->assertJsonValidationErrors('description');

    expect($product->fresh()->description)->toBe('locked');
});

it('update: an untouched non-editable field is a harmless no-op (unrelated field still updates)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("products.{$ability}");
    }

    $role = Role::create(['name' => 'product-description-locked-2']);
    $role->givePermissionTo(['products.view', 'products.update']);
    $role->fieldPermissions()->create([
        'resource' => 'products',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $product = Product::factory()->create(['name' => 'Before', 'description' => 'keep']);
    Sanctum::actingAs($actor);

    // Re-submits the SAME persisted value on the locked field — a harmless
    // no-op, not a "change" — while a different, editable field updates.
    $this->patchJson("/api/products/{$product->id}", ['name' => 'After', 'description' => 'keep'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');
});

// ---------------------------------------------------------------------------
// vat_rate_id — EnforcesFieldPermissions parity with the pre-existing fields
// ---------------------------------------------------------------------------

it('update: vat_rate_id made non-editable by the DB matrix and changed → 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("products.{$ability}");
    }

    $role = Role::create(['name' => 'product-vat-rate-locked']);
    $role->givePermissionTo(['products.view', 'products.update']);
    $role->fieldPermissions()->create([
        'resource' => 'products',
        'field' => 'vat_rate_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $vatRate = VatRate::factory()->create();
    $product = Product::factory()->create(['vat_rate_id' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['vat_rate_id' => $vatRate->id])
        ->assertStatus(422)->assertJsonValidationErrors('vat_rate_id');

    expect($product->fresh()->vat_rate_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 products.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "products.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by products.view
// ---------------------------------------------------------------------------

it('navigation: the products node only shows with products.view', function () {
    Permission::findOrCreate('products.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('products');

    $withView = User::factory()->create();
    $withView->givePermissionTo('products.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('products');
});
