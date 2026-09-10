<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `simplified_offer_line` (spec 0114): the branch-ROOT owned flag deciding
 * whether an offer line written in Gestione Richieste under the branch drops
 * the quantity/unit-price/VAT controls, the server freezing those values
 * from the picked product. Same inheritance contract as `generates_contract`
 * — root authors, subtree mirrors, a divergent child write is a 422 — so
 * this suite mirrors ProductCategoryGeneratesContractTest's shape. What the
 * flag DOES to a request-management offer line lives outside this suite
 * (RequestCreationService / RequestOfferLineWriter).
 *
 * Covers AC-001..AC-006 of spec 0114; AC-007 (seeder) lives in
 * QualificaCatalogRootRulesTest.
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

it('AC-001: flipping the root flag cascades to every descendant in one operation', function () {
    $root = ProductCategory::factory()->create(['simplified_offer_line' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['simplified_offer_line' => false]);
    $grandchild = ProductCategory::factory()->childOf($child)->create(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => true])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true);

    expect($child->fresh()->simplified_offer_line)->toBeTrue()
        ->and($grandchild->fresh()->simplified_offer_line)->toBeTrue();
});

it('AC-002: a child with a divergent value is rejected with a 422 and nothing is persisted', function () {
    $root = ProductCategory::factory()->create(['simplified_offer_line' => false]);
    $child = ProductCategory::factory()->childOf($root)->create(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['simplified_offer_line' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This category inherits the simplified offer-line rule from its root category and cannot define its own.');

    expect($child->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-003: reparenting a subtree under a simplified root makes the whole subtree adopt true', function () {
    $plainRoot = ProductCategory::factory()->create(['simplified_offer_line' => false]);
    $simplifiedRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    $moved = ProductCategory::factory()->childOf($plainRoot)->create(['simplified_offer_line' => false]);
    $movedChild = ProductCategory::factory()->childOf($moved)->create(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$moved->id}", ['parent_id' => $simplifiedRoot->id])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true);

    expect($movedChild->fresh()->simplified_offer_line)->toBeTrue()
        ->and($plainRoot->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-004: creating a child with a divergent value is rejected with a 422 and nothing is persisted (same no-override guard as the four siblings)', function () {
    $simplifiedRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Corso Qualifica',
        'parent_id' => $simplifiedRoot->id,
        'simplified_offer_line' => false,
    ])->assertStatus(422);

    expect(ProductCategory::query()->where('name', 'Corso Qualifica')->exists())->toBeFalse();
});

it('AC-004: a child created with the key absent is born with the root value', function () {
    $simplifiedRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Corso Qualifica', 'parent_id' => $simplifiedRoot->id])
        ->assertCreated()
        ->assertJsonPath('data.simplified_offer_line', true);
});

it('AC-004: a fresh root created with the key absent is born false', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Consulenza Fiscale'])
        ->assertCreated()
        ->assertJsonPath('data.simplified_offer_line', false);
});

it('create: a child echoing back the inherited value is accepted as a no-op', function () {
    $simplifiedRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', [
        'name' => 'Corso Qualifica',
        'parent_id' => $simplifiedRoot->id,
        'simplified_offer_line' => true,
    ])->assertCreated()->assertJsonPath('data.simplified_offer_line', true);
});

it('AC-005: show exposes the effective value and the source category — null on a root, the root on a descendant', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'simplified_offer_line' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true)
        ->assertJsonPath('data.simplified_offer_line_source_category', null);

    $this->getJson("/api/product-categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true)
        ->assertJsonPath('data.simplified_offer_line_source_category.id', $root->id)
        ->assertJsonPath('data.simplified_offer_line_source_category.name', 'Formazione');
});

it('AC-006: tree nodes carry the effective flag on every level', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'simplified_offer_line' => true]);
    ProductCategory::factory()->childOf($root)->create(['name' => 'GOL - Lazio', 'simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $tree = collect($this->getJson('/api/product-categories/tree')->assertOk()->json('data'));
    $rootNode = $tree->firstWhere('name', 'Formazione');

    expect($rootNode['simplified_offer_line'])->toBeTrue()
        ->and($rootNode['children'][0]['simplified_offer_line'])->toBeTrue();
});

it('the field is exposed in the permissions catalogue', function () {
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->getJson("/api/product-categories/{$root->id}")
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', false)
        ->assertJsonPath('permissions.fields.simplified_offer_line.visible', true)
        ->assertJsonPath('permissions.fields.simplified_offer_line.editable', true);
});

it('the grid row carries the flag', function () {
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $row = collect($this->postJson('/api/tables/product-categories/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))->firstWhere('name', 'Formazione');

    expect($row['simplified_offer_line'])->toBeTrue()
        ->and($row['id'])->toBe($root->id);
});
