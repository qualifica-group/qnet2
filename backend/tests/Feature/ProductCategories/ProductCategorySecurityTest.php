<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 product-categories.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "product-categories.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by product-categories.view
// ---------------------------------------------------------------------------

it('navigation: the product-categories node only shows with product-categories.view', function () {
    Permission::findOrCreate('product-categories.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('product-categories');

    $withView = User::factory()->create();
    $withView->givePermissionTo('product-categories.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('product-categories');
});

// ---------------------------------------------------------------------------
// AC-012 — authz on business_function_id (spec 0023)
// ---------------------------------------------------------------------------

it('update: 403 without product-categories.update takes precedence over the business_function_id write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("product-categories.{$ability}");
    }

    $actor = User::factory()->create();
    $target = ProductCategory::factory()->create();
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['business_function_id' => $function->id])
        ->assertForbidden();
});

it('update: business_function_id editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("product-categories.{$ability}");
    }

    $role = Role::create(['name' => 'category-function-locked']);
    $role->givePermissionTo(['product-categories.view', 'product-categories.update']);
    $role->fieldPermissions()->create([
        'resource' => 'product-categories',
        'field' => 'business_function_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $function = BusinessFunction::factory()->create();
    $target = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['business_function_id' => $function->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_function_id');

    expect($target->fresh()->business_function_id)->toBeNull();
});
