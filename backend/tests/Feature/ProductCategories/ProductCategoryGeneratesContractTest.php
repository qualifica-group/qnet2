<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `generates_contract` (spec 0091): the branch-ROOT owned flag deciding
 * whether a positively closed offer of the branch opens a contract at all.
 * Same inheritance contract as `single_quote_per_opportunity` — root authors,
 * subtree mirrors, a divergent child write is a 422 — so this suite mirrors
 * ProductCategorySingleQuoteTest's shape. What the flag DOES lives in
 * tests/Feature/Contracts/ContractCategoryGateTest.php.
 *
 * The one asymmetry worth its own case: this flag defaults to TRUE, because
 * "every deal opens a contract" is the behaviour that predates it.
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

it('AC-001: a root category persists its own generates_contract flag', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Formazione', 'generates_contract' => false])
        ->assertCreated()
        ->assertJsonPath('data.generates_contract', false);

    expect(ProductCategory::query()->firstWhere('name', 'Formazione')->generates_contract)->toBeFalse();
});

it('AC-002: omitting the flag on a fresh root defaults to true (pre-existing behaviour)', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Consulenza'])
        ->assertCreated()
        ->assertJsonPath('data.generates_contract', true);
});

it('AC-003: a child inherits the root flag even when the payload omits it', function () {
    $root = ProductCategory::factory()->create(['generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'GOL - Lazio', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.generates_contract', false);
});

it('AC-004: 422 when a child submits a flag differing from the inherited one, nothing persisted', function () {
    $root = ProductCategory::factory()->create(['generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'generates_contract' => true,
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'GOL - Lazio')->exists())->toBeFalse();
});

it('create: a child echoing back the inherited flag is accepted as a no-op', function () {
    $root = ProductCategory::factory()->create(['generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'GOL - Lazio',
        'parent_id' => $root->id,
        'generates_contract' => false,
    ])->assertCreated()->assertJsonPath('data.generates_contract', false);
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('AC-004: a category with a parent may not write the flag', function () {
    $root = ProductCategory::factory()->create(['generates_contract' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['generates_contract' => true])
        ->assertStatus(422);

    expect($child->fresh()->generates_contract)->toBeFalse();
});

it('AC-005: flipping the root flag cascades to every descendant in one operation', function () {
    $root = ProductCategory::factory()->create(['generates_contract' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['generates_contract' => true]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['generates_contract' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['generates_contract' => false])
        ->assertOk()
        ->assertJsonPath('data.generates_contract', false);

    expect($child->fresh()->generates_contract)->toBeFalse()
        ->and($grandchild->fresh()->generates_contract)->toBeFalse();
});

it('AC-006: reparenting under a differently-flagged root re-aligns the whole moved subtree', function () {
    $contractRoot = ProductCategory::factory()->create(['generates_contract' => true]);
    $trainingRoot = ProductCategory::factory()->create(['generates_contract' => false]);
    $moved = ProductCategory::factory()->childOf($contractRoot)->create(['generates_contract' => true]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['generates_contract' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $trainingRoot->id])
        ->assertOk()
        ->assertJsonPath('data.generates_contract', false);

    expect($movedChild->fresh()->generates_contract)->toBeFalse()
        ->and($contractRoot->fresh()->generates_contract)->toBeTrue();
});

// ---------------------------------------------------------------------------
// read side
// ---------------------------------------------------------------------------

it('AC-007: show exposes the source category — null on a root, the root on a descendant', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'generates_contract' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['generates_contract' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.generates_contract', false)
        ->assertJsonPath('data.generates_contract_source_category', null);

    $this->getJson("/api/product-categories/{$grandchild->id}")
        ->assertOk()
        ->assertJsonPath('data.generates_contract', false)
        ->assertJsonPath('data.generates_contract_source_category.id', $root->id)
        ->assertJsonPath('data.generates_contract_source_category.name', 'Formazione');
});

it('the field is exposed in the permissions catalogue', function () {
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.generates_contract', true)
        ->assertJsonPath('permissions.fields.generates_contract.visible', true)
        ->assertJsonPath('permissions.fields.generates_contract.editable', true);
});

it('tree nodes carry the effective flag on every level', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'generates_contract' => false]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio', 'generates_contract' => false]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $tree = collect($this->getJson('/api/product-categories/tree')->assertOk()->json('data'));
    $rootNode = $tree->firstWhere('name', 'Formazione');

    expect($rootNode['generates_contract'])->toBeFalse()
        ->and($rootNode['children'][0]['generates_contract'])->toBeFalse();
});

it('AC-015: the grid row carries both root-owned booleans', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'generates_contract' => false, 'single_quote_per_opportunity' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $row = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))->firstWhere('name', 'Formazione');

    expect($row['generates_contract'])->toBeFalse()
        ->and($row['single_quote_per_opportunity'])->toBeTrue()
        ->and($row['id'])->toBe($root->id);
});
