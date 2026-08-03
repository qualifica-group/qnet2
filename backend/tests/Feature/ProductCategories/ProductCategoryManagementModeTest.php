<?php

use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — AC-001/002/003
// ---------------------------------------------------------------------------

it('AC-001: a root category persists its own management_mode', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Formazione', 'management_mode' => 'single'])
        ->assertCreated()
        ->assertJsonPath('data.management_mode', 'single');

    expect(ProductCategory::query()->firstWhere('name', 'Formazione')->management_mode->value)->toBe('single');
});

it('AC-002: a child inherits the root management_mode even when the payload omits it', function () {
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'GOL - Lazio', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.management_mode', 'single');
});

it('AC-003: create — 422 when a child submits a mode differing from the inherited one, nothing persisted', function () {
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'management_mode' => 'multiple',
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'GOL - Lazio')->exists())->toBeFalse();
});

it('create: a child echoing back the inherited mode is accepted as a no-op', function () {
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'management_mode' => 'single',
    ])->assertCreated()->assertJsonPath('data.management_mode', 'single');
});

it('AC-001: omitting management_mode on a fresh root defaults to multiple (D-8)', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Consulenza'])
        ->assertCreated()
        ->assertJsonPath('data.management_mode', 'multiple');
});

it('create: an invalid management_mode value is rejected (422)', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'management_mode' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('management_mode');
});

// ---------------------------------------------------------------------------
// update — AC-003/004/005
// ---------------------------------------------------------------------------

it('AC-003: update — a category with a parent may not write the mode', function () {
    $root = ProductCategory::factory()->create(['management_mode' => 'single']);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['management_mode' => 'multiple'])
        ->assertStatus(422);

    expect($child->fresh()->management_mode->value)->toBe('single');
});

it('AC-004: flipping the root mode cascades to every descendant in one operation', function () {
    $root = ProductCategory::factory()->create(['management_mode' => 'multiple']);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => 'multiple']);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['management_mode' => 'multiple']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['management_mode' => 'single'])
        ->assertOk()
        ->assertJsonPath('data.management_mode', 'single');

    expect($child->fresh()->management_mode->value)->toBe('single')
        ->and($grandchild->fresh()->management_mode->value)->toBe('single');
});

it('AC-005: reparenting under a different-mode root re-aligns the whole moved subtree', function () {
    $singleRoot = ProductCategory::factory()->create(['management_mode' => 'single']);
    $multipleRoot = ProductCategory::factory()->create(['management_mode' => 'multiple']);
    $moved = ProductCategory::factory()->childOf($multipleRoot)->create(['management_mode' => 'multiple']);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['management_mode' => 'multiple']);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $singleRoot->id])
        ->assertOk()
        ->assertJsonPath('data.management_mode', 'single');

    expect($movedChild->fresh()->management_mode->value)->toBe('single')
        ->and($multipleRoot->fresh()->management_mode->value)->toBe('multiple');
});

// ---------------------------------------------------------------------------
// read side — AC-002/006, source category, table row
// ---------------------------------------------------------------------------

it('AC-002: show exposes management_mode_source_category — null on a root, the root on a descendant', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'management_mode' => 'single']);
    $child = ProductCategory::factory()->childOf($root)->create(['management_mode' => 'single']);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.management_mode', 'single')
        ->assertJsonPath('data.management_mode_source_category', null);

    $this->getJson("/api/product-categories/{$grandchild->id}")
        ->assertOk()
        ->assertJsonPath('data.management_mode', 'single')
        ->assertJsonPath('data.management_mode_source_category.id', $root->id)
        ->assertJsonPath('data.management_mode_source_category.name', 'Formazione');
});

it('AC-006: a pre-existing root reads as multiple and the field is exposed in the permissions catalogue', function () {
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.management_mode', 'multiple')
        ->assertJsonPath('permissions.fields.management_mode.visible', true)
        ->assertJsonPath('permissions.fields.management_mode.editable', true);
});

it('table: rows carry the effective management_mode', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'management_mode' => 'single']);
    ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio', 'management_mode' => 'single']);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $rows = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->keyBy('name');

    expect($rows['Formazione']['management_mode'])->toBe('single')
        ->and($rows['GOL - Lazio']['management_mode'])->toBe('single');
});

// ---------------------------------------------------------------------------
// AC-007 — authorization
// ---------------------------------------------------------------------------

it('AC-007: 403 without product-categories.update takes precedence over the management_mode write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("product-categories.{$ability}");
    }

    $actor = User::factory()->create();
    $target = ProductCategory::factory()->create(['management_mode' => 'multiple']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['management_mode' => 'single'])
        ->assertForbidden();

    expect($target->fresh()->management_mode->value)->toBe('multiple');
});

it('AC-007: management_mode editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("product-categories.{$ability}");
    }

    $role = Role::create(['name' => 'category-mode-locked']);
    $role->givePermissionTo(['product-categories.view', 'product-categories.update']);
    $role->fieldPermissions()->create([
        'resource' => 'product-categories',
        'field' => 'management_mode',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = ProductCategory::factory()->create(['management_mode' => 'multiple']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$target->id}", ['management_mode' => 'single'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('management_mode');

    expect($target->fresh()->management_mode->value)->toBe('multiple');
});
