<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
// schema + default
// ---------------------------------------------------------------------------

it('requires_quote is a boolean column defaulting to false', function () {
    expect(Schema::hasColumn('product_categories', 'requires_quote'))->toBeTrue();

    expect(ProductCategory::factory()->create()->requires_quote)->toBeFalse();
});

// ---------------------------------------------------------------------------
// create — the root authors the flag, a child takes the root's value
// ---------------------------------------------------------------------------

it('create: a root category persists its own flag', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Electronics', 'requires_quote' => true])
        ->assertCreated()
        ->assertJsonPath('data.requires_quote', true);

    expect(ProductCategory::query()->firstWhere('name', 'Electronics')->requires_quote)->toBeTrue();
});

it('create: a child inherits the root flag even when the payload omits it', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Wiring', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.requires_quote', true);
});

it('create: a grandchild inherits the ROOT flag, not an intermediate ancestor value', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Sockets', 'parent_id' => $child->id])
        ->assertCreated()
        ->assertJsonPath('data.requires_quote', true);
});

it('create: 422 when a child submits a flag differing from the inherited one', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Wiring',
        'parent_id' => $root->id,
        'requires_quote' => false,
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'Wiring')->exists())->toBeFalse();
});

it('create: a child echoing back the inherited flag is accepted as a no-op', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Wiring',
        'parent_id' => $root->id,
        'requires_quote' => true,
    ])->assertCreated()->assertJsonPath('data.requires_quote', true);
});

// ---------------------------------------------------------------------------
// update — the root's edit cascades over the whole subtree
// ---------------------------------------------------------------------------

it('update: flipping the root flag cascades to every descendant', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['requires_quote' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['requires_quote' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['requires_quote' => true])
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true);

    expect($child->fresh()->requires_quote)->toBeTrue()
        ->and($grandchild->fresh()->requires_quote)->toBeTrue();
});

it('update: a category with a parent may not write the flag', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['requires_quote' => false])
        ->assertStatus(422);

    expect($child->fresh()->requires_quote)->toBeTrue();
});

// ---------------------------------------------------------------------------
// reparenting — the subtree adopts its NEW root's flag
// ---------------------------------------------------------------------------

it('update: moving a branch under a quoted root re-aligns the whole moved subtree', function () {
    $quotedRoot = ProductCategory::factory()->create(['requires_quote' => true]);
    $plainRoot = ProductCategory::factory()->create(['requires_quote' => false]);
    $moved = ProductCategory::factory()->childOf($plainRoot)->create(['requires_quote' => false]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['requires_quote' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $quotedRoot->id])
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true);

    expect($movedChild->fresh()->requires_quote)->toBeTrue()
        ->and($plainRoot->fresh()->requires_quote)->toBeFalse();
});

it('update: promoting a branch to root leaves it owning the flag it arrived with', function () {
    $quotedRoot = ProductCategory::factory()->create(['requires_quote' => true]);
    $promoted = ProductCategory::factory()->childOf($quotedRoot)->create(['requires_quote' => true]);
    $promotedChild = ProductCategory::factory()->childOf($promoted)->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$promoted->id}", ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true);

    expect($promotedChild->fresh()->requires_quote)->toBeTrue();

    // Now a root, it authors the flag — and its own subtree follows it.
    $this->patchJson("/api/product-categories/{$promoted->id}", ['requires_quote' => false])->assertOk();

    expect($promotedChild->fresh()->requires_quote)->toBeFalse()
        ->and($quotedRoot->fresh()->requires_quote)->toBeTrue();
});

it('bulk move: the moved categories adopt the destination root flag', function () {
    $quotedRoot = ProductCategory::factory()->create(['requires_quote' => true]);
    $first = ProductCategory::factory()->create(['requires_quote' => false]);
    $second = ProductCategory::factory()->create(['requires_quote' => false]);
    $secondChild = ProductCategory::factory()->childOf($second)->create(['requires_quote' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$first->id, $second->id],
        'parent_id' => $quotedRoot->id,
    ])->assertOk();

    expect($first->fresh()->requires_quote)->toBeTrue()
        ->and($second->fresh()->requires_quote)->toBeTrue()
        ->and($secondChild->fresh()->requires_quote)->toBeTrue();
});

// ---------------------------------------------------------------------------
// read side — detail, source category, table row, field permissions
// ---------------------------------------------------------------------------

it('show: a child reports the ROOT it inherits the flag from, a root reports none', function () {
    $root = ProductCategory::factory()->create(['name' => 'Electronics', 'requires_quote' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['requires_quote' => true]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true)
        ->assertJsonPath('data.requires_quote_source_category', null);

    $this->getJson("/api/product-categories/{$grandchild->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_quote', true)
        ->assertJsonPath('data.requires_quote_source_category.id', $root->id)
        ->assertJsonPath('data.requires_quote_source_category.name', 'Electronics');
});

it('update: promoting a child to root and setting the flag in ONE save is allowed', function () {
    $quotedRoot = ProductCategory::factory()->create(['requires_quote' => true]);
    $child = ProductCategory::factory()->childOf($quotedRoot)->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    // The flag is inherited at request time but owned by the END state: the
    // guard reads the SUBMITTED parent, not the persisted one.
    $this->patchJson("/api/product-categories/{$child->id}", [
        'parent_id' => null,
        'requires_quote' => false,
    ])->assertOk()->assertJsonPath('data.requires_quote', false);

    expect($quotedRoot->fresh()->requires_quote)->toBeTrue();
});

it('show: the flag is exposed in the field permissions catalogue', function () {
    $root = ProductCategory::factory()->create(['requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.requires_quote.visible', true)
        ->assertJsonPath('permissions.fields.requires_quote.editable', true);
});

it('table: rows carry the effective flag', function () {
    $root = ProductCategory::factory()->create(['name' => 'Electronics', 'requires_quote' => true]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'Wiring', 'requires_quote' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $rows = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->keyBy('name');

    expect($rows['Electronics']['requires_quote'])->toBeTrue()
        ->and($rows['Wiring']['requires_quote'])->toBeTrue();
});
