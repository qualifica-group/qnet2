<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `single_quote_per_opportunity` (user directive 2026-08-07): the branch-ROOT
 * owned flag capping an opportunity at one quote. Same inheritance contract as
 * `management_mode` — root authors, subtree mirrors, a divergent child write is
 * a 422 — so this suite mirrors ProductCategoryManagementModeTest's shape. The
 * ENFORCEMENT of the cap on quote creation lives in
 * tests/Feature/Quotes/QuoteSingleQuotePerOpportunityTest.php.
 */
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
// create
// ---------------------------------------------------------------------------

it('a root category persists its own single_quote_per_opportunity flag', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Formazione', 'single_quote_per_opportunity' => true])
        ->assertCreated()
        ->assertJsonPath('data.single_quote_per_opportunity', true);

    expect(ProductCategory::query()->firstWhere('name', 'Formazione')->single_quote_per_opportunity)->toBeTrue();
});

it('omitting the flag on a fresh root defaults to false (pre-existing behaviour)', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Consulenza'])
        ->assertCreated()
        ->assertJsonPath('data.single_quote_per_opportunity', false);
});

it('a child inherits the root flag even when the payload omits it', function () {
    $root = ProductCategory::factory()->create(['single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'GOL - Lazio', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.single_quote_per_opportunity', true);
});

it('create: 422 when a child submits a flag differing from the inherited one, nothing persisted', function () {
    $root = ProductCategory::factory()->create(['single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'single_quote_per_opportunity' => false,
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'GOL - Lazio')->exists())->toBeFalse();
});

it('create: a child echoing back the inherited flag is accepted as a no-op', function () {
    $root = ProductCategory::factory()->create(['single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'single_quote_per_opportunity' => true,
    ])->assertCreated()->assertJsonPath('data.single_quote_per_opportunity', true);
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('update: a category with a parent may not write the flag', function () {
    $root = ProductCategory::factory()->create(['single_quote_per_opportunity' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['single_quote_per_opportunity' => false])
        ->assertStatus(422);

    expect($child->fresh()->single_quote_per_opportunity)->toBeTrue();
});

it('flipping the root flag cascades to every descendant in one operation', function () {
    $root = ProductCategory::factory()->create(['single_quote_per_opportunity' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['single_quote_per_opportunity' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['single_quote_per_opportunity' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['single_quote_per_opportunity' => true])
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', true);

    expect($child->fresh()->single_quote_per_opportunity)->toBeTrue()
        ->and($grandchild->fresh()->single_quote_per_opportunity)->toBeTrue();
});

it('reparenting under a differently-flagged root re-aligns the whole moved subtree', function () {
    $strictRoot = ProductCategory::factory()->create(['single_quote_per_opportunity' => true]);
    $looseRoot = ProductCategory::factory()->create(['single_quote_per_opportunity' => false]);
    $moved = ProductCategory::factory()->childOf($looseRoot)->create(['single_quote_per_opportunity' => false]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['single_quote_per_opportunity' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $strictRoot->id])
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', true);

    expect($movedChild->fresh()->single_quote_per_opportunity)->toBeTrue()
        ->and($looseRoot->fresh()->single_quote_per_opportunity)->toBeFalse();
});

// ---------------------------------------------------------------------------
// read side
// ---------------------------------------------------------------------------

it('show exposes the source category — null on a root, the root on a descendant', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'single_quote_per_opportunity' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['single_quote_per_opportunity' => true]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', true)
        ->assertJsonPath('data.single_quote_per_opportunity_source_category', null);

    $this->getJson("/api/product-categories/{$grandchild->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', true)
        ->assertJsonPath('data.single_quote_per_opportunity_source_category.id', $root->id)
        ->assertJsonPath('data.single_quote_per_opportunity_source_category.name', 'Formazione');
});

it('the field is exposed in the permissions catalogue', function () {
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.single_quote_per_opportunity', false)
        ->assertJsonPath('permissions.fields.single_quote_per_opportunity.visible', true)
        ->assertJsonPath('permissions.fields.single_quote_per_opportunity.editable', true);
});

it('tree nodes carry the effective flag on every level', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'single_quote_per_opportunity' => true]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio', 'single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $tree = collect($this->getJson('/api/product-categories/tree')->assertOk()->json('data'));
    $rootNode = $tree->firstWhere('name', 'Formazione');

    expect($rootNode['single_quote_per_opportunity'])->toBeTrue()
        ->and($rootNode['children'][0]['single_quote_per_opportunity'])->toBeTrue();
});
