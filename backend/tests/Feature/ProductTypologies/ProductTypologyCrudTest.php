<?php

use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\User;
use Database\Seeders\ProductTypologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productTypologyUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productTypologyUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("product-typologies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-typologies.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// migration + seed (AC-002, AC-004)
// ---------------------------------------------------------------------------

it('migration: creates the default typology row (AC-002)', function () {
    $this->assertDatabaseHas('product_typologies', ['code' => 'institution', 'name' => 'Ente']);
});

it('seeder: produces Ente and Consulenza and is idempotent (AC-004)', function () {
    $this->seed(ProductTypologySeeder::class);
    $this->seed(ProductTypologySeeder::class);

    expect(ProductTypology::whereIn('code', ['institution', 'consultancy'])->count())->toBe(2);
    $this->assertDatabaseHas('product_typologies', ['code' => 'consultancy', 'name' => 'Consulenza']);
});

it('seeder: never overwrites a rename made through the module (AC-004)', function () {
    ProductTypology::where('code', 'institution')->update(['name' => 'Ente Pubblico']);

    $this->seed(ProductTypologySeeder::class);

    $this->assertDatabaseHas('product_typologies', ['code' => 'institution', 'name' => 'Ente Pubblico']);
});

// ---------------------------------------------------------------------------
// create — POST /api/product-typologies (AC-010..012)
// ---------------------------------------------------------------------------

it('create: 201 + persists all fields (AC-010)', function () {
    Sanctum::actingAs(productTypologyUserWith(['create']));

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'description' => 'Corsi'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Formazione')
        ->assertJsonPath('data.code', 'training')
        ->assertJsonPath('data.description', 'Corsi')
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'description', 'created_at', 'updated_at'], 'permissions']);

    $this->assertDatabaseHas('product_typologies', ['name' => 'Formazione', 'code' => 'training']);
});

it('create: 201 with only name+code, description defaults to null (AC-010)', function () {
    Sanctum::actingAs(productTypologyUserWith(['create']));

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

it('create: 422 when name/code already exist (AC-011)', function () {
    Sanctum::actingAs(productTypologyUserWith(['create']));
    ProductTypology::factory()->create(['name' => 'Taken Name', 'code' => 'taken_code']);

    $this->postJson('/api/product-typologies', ['name' => 'Taken Name', 'code' => 'fresh_1'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/product-typologies', ['name' => 'Fresh Name', 'code' => 'taken_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    expect(ProductTypology::where('name', 'Taken Name')->count())->toBe(1);
});

it('create: 422 when code is out of the snake_case regex (AC-012)', function (string $badCode) {
    Sanctum::actingAs(productTypologyUserWith(['create']));

    $this->postJson('/api/product-typologies', ['name' => 'Fresh '.$badCode, 'code' => $badCode])
        ->assertStatus(422)->assertJsonValidationErrors('code');
})->with(['Training', 'training-kind', '1training', 'training kind', '_training']);

// ---------------------------------------------------------------------------
// update — PATCH (AC-013)
// ---------------------------------------------------------------------------

it('update: renames without touching code', function () {
    Sanctum::actingAs(productTypologyUserWith(['update']));
    $typology = ProductTypology::factory()->create(['name' => 'Old', 'code' => 'stable_code']);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.code', 'stable_code');
});

it('update: 422 when code is present in the payload, even for a super-admin (AC-013)', function () {
    $actor = productTypologyUserWith(['update']);
    $typology = ProductTypology::factory()->create(['code' => 'stable_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['code' => 'other_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    expect($typology->fresh()->code)->toBe('stable_code');
});

// ---------------------------------------------------------------------------
// delete guard (AC-014..016)
// ---------------------------------------------------------------------------

it('delete: 204 when the typology is not referenced (AC-014)', function () {
    Sanctum::actingAs(productTypologyUserWith(['delete']));
    $typology = ProductTypology::factory()->create();

    $this->deleteJson("/api/product-typologies/{$typology->id}")->assertNoContent();

    $this->assertDatabaseMissing('product_typologies', ['id' => $typology->id]);
});

it('delete: 409 with an explanatory message when a product uses it, and NO product is removed (AC-015)', function () {
    Sanctum::actingAs(productTypologyUserWith(['delete']));
    $typology = ProductTypology::factory()->create();
    $product = Product::factory()->create(['product_typology_id' => $typology->id]);

    $this->deleteJson("/api/product-typologies/{$typology->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This product typology is used by a product and cannot be deleted.');

    $this->assertDatabaseHas('product_typologies', ['id' => $typology->id]);
    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

it('delete: the generic bulk-delete applies the same guard (AC-016)', function () {
    $actor = productTypologyUserWith(['viewAny', 'delete']);
    $used = ProductTypology::factory()->create();
    $free = ProductTypology::factory()->create();
    Product::factory()->create(['product_typology_id' => $used->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/product-typologies/bulk-delete', ['ids' => [$used->id, $free->id]]);

    $this->assertDatabaseHas('product_typologies', ['id' => $used->id]);
    $this->assertDatabaseMissing('product_typologies', ['id' => $free->id]);
});

// ---------------------------------------------------------------------------
// authorization (AC-021)
// ---------------------------------------------------------------------------

it('403 on every CRUD endpoint without the matching permission (AC-021)', function () {
    $typology = ProductTypology::factory()->create();
    Sanctum::actingAs(productTypologyUserWith([]));

    $this->getJson("/api/product-typologies/{$typology->id}")->assertForbidden();
    $this->postJson('/api/product-typologies', ['name' => 'X', 'code' => 'x_code'])->assertForbidden();
    $this->patchJson("/api/product-typologies/{$typology->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/product-typologies/{$typology->id}")->assertForbidden();
});

it('for-select: 200 without product-typologies.viewAny, ordered by name (AC-021)', function () {
    ProductTypology::query()->delete();
    ProductTypology::factory()->create(['name' => 'Zeta', 'code' => 'zeta']);
    ProductTypology::factory()->create(['name' => 'Alfa', 'code' => 'alfa']);
    Sanctum::actingAs(productTypologyUserWith([]));

    $this->getJson('/api/product-typologies/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.label', 'Alfa')
        ->assertJsonPath('items.1.label', 'Zeta');
});
