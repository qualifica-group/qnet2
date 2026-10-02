<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0188: `simplified_offer_line` becomes overridable per node with
 * nearest-ancestor semantics. `simplified_offer_line_override` is the node's
 * own declaration (null = inherit), `simplified_offer_line` stays the
 * denormalised effective value. AC-001..AC-008 and AC-010; AC-009 lives in
 * RequestManagement/SimplifiedOfferLineOverrideFreezeTest, AC-011 in the
 * seeder tests.
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

/** Formazione (root, true) -> F -> N, plus a sibling S of F. */
function overrideBranch(bool $rootValue = true): array
{
    $root = ProductCategory::factory()->create(['name' => 'Formazione', 'simplified_offer_line' => $rootValue]);
    $f = ProductCategory::factory()->childOf($root)->create(['name' => 'F', 'simplified_offer_line' => $rootValue]);
    $n = ProductCategory::factory()->childOf($f)->create(['name' => 'N', 'simplified_offer_line' => $rootValue]);
    $s = ProductCategory::factory()->childOf($root)->create(['name' => 'S', 'simplified_offer_line' => $rootValue]);

    return compact('root', 'f', 'n', 's');
}

it('AC-001: the column exists, defaults to null, and the rollback removes it', function () {
    expect(Schema::hasColumn('product_categories', 'simplified_offer_line_override'))->toBeTrue();

    $root = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    expect($root->fresh()->simplified_offer_line_override)->toBeNull()
        ->and($root->fresh()->simplified_offer_line)->toBeTrue();

    $migration = require database_path('migrations/2026_10_02_100000_add_simplified_offer_line_override_to_product_categories_table.php');
    $migration->down();
    expect(Schema::hasColumn('product_categories', 'simplified_offer_line_override'))->toBeFalse();
    $migration->up();
});

it('AC-002: an override false on F flips F and N, leaves siblings, and reports no source', function () {
    ['root' => $root, 'f' => $f, 'n' => $n, 's' => $s] = overrideBranch();
    Sanctum::actingAs(productCategoryUserWith(['update', 'view']));

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line_override' => false])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', false)
        ->assertJsonPath('data.simplified_offer_line_override', false);

    $this->getJson("/api/product-categories/{$f->id}")
        ->assertJsonPath('data.simplified_offer_line_source_category', null);

    expect($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse()
        ->and($s->fresh()->simplified_offer_line)->toBeTrue()
        ->and($root->fresh()->simplified_offer_line)->toBeTrue();
});

it('AC-003: clearing the override restores the inherited value and the source is the root', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update', 'view']));

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line_override' => null])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true)
        ->assertJsonPath('data.simplified_offer_line_override', null);

    expect($n->fresh()->simplified_offer_line)->toBeTrue();

    $this->getJson("/api/product-categories/{$f->id}")
        ->assertJsonPath('data.simplified_offer_line_source_category.name', 'Formazione');
});

it('AC-004: root changes reach siblings but never an overriding branch', function () {
    ['root' => $root, 'f' => $f, 'n' => $n, 's' => $s] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => false])->assertOk();
    expect($s->fresh()->simplified_offer_line)->toBeFalse()
        ->and($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();

    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => true])->assertOk();
    expect($s->fresh()->simplified_offer_line)->toBeTrue()
        ->and($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-004: a grandchild under an overriding node follows that node, not the root', function () {
    ['root' => $root, 'f' => $f, 'n' => $n] = overrideBranch(false);
    $f->update(['simplified_offer_line_override' => true, 'simplified_offer_line' => true]);
    $n->update(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => true])->assertOk();
    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => false])->assertOk();

    expect($f->fresh()->simplified_offer_line)->toBeTrue()
        ->and($n->fresh()->simplified_offer_line)->toBeTrue();
});

it('AC-005: show on the grandchild reports the effective value and F as source', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->getJson("/api/product-categories/{$n->id}")
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', false)
        ->assertJsonPath('data.simplified_offer_line_override', null)
        ->assertJsonPath('data.simplified_offer_line_source_category.id', $f->id);
});

it('AC-006: a root cannot take an override (create and update), nothing is persisted', function () {
    $root = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line_override' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A root category sets the simplified offer-line rule directly.');

    $this->postJson('/api/product-categories', ['name' => 'Radice', 'simplified_offer_line_override' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A root category sets the simplified offer-line rule directly.');

    expect($root->fresh()->simplified_offer_line_override)->toBeNull()
        ->and(ProductCategory::query()->where('name', 'Radice')->exists())->toBeFalse();
});

it('AC-006: a child value diverging from its effective one is a 422, a matching one is accepted', function () {
    ['f' => $f] = overrideBranch();
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This category inherits the simplified offer-line rule; set its override instead.');
    expect($f->fresh()->simplified_offer_line)->toBeTrue();

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line' => true])->assertOk();

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line_override' => false, 'simplified_offer_line' => false])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', false);

    $this->postJson('/api/product-categories', ['name' => 'Figlia', 'parent_id' => $f->id, 'simplified_offer_line' => true])
        ->assertStatus(422);
});

it('create: a child born with an override takes it as effective value, without it inherits', function () {
    ['root' => $root] = overrideBranch();
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Corsi', 'parent_id' => $root->id, 'simplified_offer_line_override' => false])
        ->assertCreated()
        ->assertJsonPath('data.simplified_offer_line', false)
        ->assertJsonPath('data.simplified_offer_line_override', false);

    $this->postJson('/api/product-categories', ['name' => 'Altro', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.simplified_offer_line', true)
        ->assertJsonPath('data.simplified_offer_line_override', null);
});

it('AC-007: moving an overriding node under another root keeps override and effective value', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    $other = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$f->id}", ['parent_id' => $other->id])->assertOk();

    expect($f->fresh()->simplified_offer_line_override)->toBeFalse()
        ->and($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-007: a bulk move goes through the same path (under another root and to the root)', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    $other = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->postJson('/api/product-categories/bulk-move', ['category_ids' => [$f->id], 'parent_id' => $other->id])->assertOk();

    expect($f->fresh()->parent_id)->toBe($other->id)
        ->and($f->fresh()->simplified_offer_line_override)->toBeFalse()
        ->and($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();

    $this->postJson('/api/product-categories/bulk-move', ['category_ids' => [$f->id], 'parent_id' => null])->assertOk();

    expect($f->fresh()->parent_id)->toBeNull()
        ->and($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($f->fresh()->simplified_offer_line_override)->toBeNull()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-007: an overriding node promoted to root keeps its effective value and drops the override', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$f->id}", ['parent_id' => null])->assertOk();

    expect($f->fresh()->simplified_offer_line)->toBeFalse()
        ->and($f->fresh()->simplified_offer_line_override)->toBeNull()
        ->and($n->fresh()->simplified_offer_line)->toBeFalse();
});

it('AC-007: a false root moved under a true root inherits true', function () {
    $falseRoot = ProductCategory::factory()->create(['simplified_offer_line' => false]);
    $child = ProductCategory::factory()->childOf($falseRoot)->create(['simplified_offer_line' => false]);
    $trueRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$falseRoot->id}", ['parent_id' => $trueRoot->id])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', true)
        ->assertJsonPath('data.simplified_offer_line_override', null);

    expect($child->fresh()->simplified_offer_line)->toBeTrue();
});

it('AC-007: a false root moved under a true root with an override sent in the same request keeps it', function () {
    $falseRoot = ProductCategory::factory()->create(['simplified_offer_line' => false]);
    $trueRoot = ProductCategory::factory()->create(['simplified_offer_line' => true]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$falseRoot->id}", ['parent_id' => $trueRoot->id, 'simplified_offer_line_override' => false])
        ->assertOk()
        ->assertJsonPath('data.simplified_offer_line', false)
        ->assertJsonPath('data.simplified_offer_line_override', false);
});

it('the resync is idempotent: already aligned rows are not rewritten', function () {
    ['root' => $root, 'f' => $f] = overrideBranch();
    Sanctum::actingAs(productCategoryUserWith(['update']));
    $before = $f->fresh()->updated_at;

    $this->travel(5)->minutes();
    $this->patchJson("/api/product-categories/{$root->id}", ['simplified_offer_line' => true])->assertOk();

    expect($f->fresh()->updated_at->equalTo($before))->toBeTrue();
});

it('AC-008: without the write permission the PATCH is 403 and the field is readonly in meta', function () {
    ['f' => $f] = overrideBranch();
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $this->patchJson("/api/product-categories/{$f->id}", ['simplified_offer_line_override' => false])->assertForbidden();
    expect($f->fresh()->simplified_offer_line_override)->toBeNull();

    $this->getJson("/api/product-categories/{$f->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.simplified_offer_line_override.visible', true)
        ->assertJsonPath('permissions.fields.simplified_offer_line_override.editable', false);
});

it('AC-010: tree nodes carry the override and the effective value', function () {
    ['f' => $f, 'n' => $n] = overrideBranch();
    $f->update(['simplified_offer_line_override' => false, 'simplified_offer_line' => false]);
    $n->update(['simplified_offer_line' => false]);
    Sanctum::actingAs(productCategoryUserWith(['viewAny']));

    $root = collect($this->getJson('/api/product-categories/tree')->assertOk()->json('data'))->firstWhere('name', 'Formazione');
    $fNode = collect($root['children'])->firstWhere('name', 'F');

    expect($root['simplified_offer_line_override'])->toBeNull()
        ->and($root['simplified_offer_line'])->toBeTrue()
        ->and($fNode['simplified_offer_line_override'])->toBeFalse()
        ->and($fNode['simplified_offer_line'])->toBeFalse()
        ->and($fNode['children'][0]['simplified_offer_line_override'])->toBeNull()
        ->and($fNode['children'][0]['simplified_offer_line'])->toBeFalse();
});
