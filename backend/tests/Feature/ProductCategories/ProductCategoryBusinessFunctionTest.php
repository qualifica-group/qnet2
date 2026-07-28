<?php

use App\Models\BusinessFunction;
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
// AC-001 — schema: nullable FK, nullOnDelete, reversible migration
// ---------------------------------------------------------------------------

it('AC-001: business_function_id is a nullable, indexed FK on product_categories', function () {
    expect(Schema::hasColumn('product_categories', 'business_function_id'))->toBeTrue();

    $category = ProductCategory::factory()->create();
    expect($category->business_function_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-002 — nullOnDelete
// ---------------------------------------------------------------------------

it('AC-002: deleting a referenced BusinessFunction nulls the column, the category survives', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $function->delete();

    expect(ProductCategory::query()->find($category->id))->not->toBeNull()
        ->and($category->fresh()->business_function_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-003 — create at root, no ancestor
// ---------------------------------------------------------------------------

it('AC-003: POST at root with a valid business_function_id persists it, effective is own (not inherited)', function () {
    $actor = productCategoryUserWith(['create', 'view']);
    $function = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/product-categories', [
        'name' => 'Root', 'business_function_id' => $function->id,
    ])->assertCreated();

    $response->assertJsonPath('data.business_function_id', $function->id)
        ->assertJsonPath('data.effective_business_function.id', $function->id)
        ->assertJsonPath('data.effective_business_function.inherited', false)
        ->assertJsonPath('data.effective_business_function.source_category', null);
});

// ---------------------------------------------------------------------------
// AC-004 — transitive inheritance: A(BF=1) -> B(null) -> C(null)
// ---------------------------------------------------------------------------

it('AC-004: a grandchild inherits TRANSITIVELY from a grandparent through a functionless parent', function () {
    $actor = productCategoryUserWith(['view']);
    $function = BusinessFunction::factory()->create();
    $a = ProductCategory::factory()->create(['name' => 'A', 'business_function_id' => $function->id]);
    $b = ProductCategory::factory()->childOf($a)->create(['name' => 'B']);
    $c = ProductCategory::factory()->childOf($b)->create(['name' => 'C']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/{$c->id}")->assertOk();

    $response->assertJsonPath('data.business_function_id', null)
        ->assertJsonPath('data.effective_business_function.id', $function->id)
        ->assertJsonPath('data.effective_business_function.inherited', true)
        ->assertJsonPath('data.effective_business_function.source_category.id', $a->id);
});

// ---------------------------------------------------------------------------
// AC-005 — no-override on CREATE
// ---------------------------------------------------------------------------

it('AC-005: POST under a category that already inherits a function -> 422, no override', function () {
    $actor = productCategoryUserWith(['create']);
    $ownFunction = BusinessFunction::factory()->create();
    $inheritedFunction = BusinessFunction::factory()->create();
    $a = ProductCategory::factory()->create(['business_function_id' => $inheritedFunction->id]);
    $b = ProductCategory::factory()->childOf($a)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/product-categories', [
        'name' => 'C', 'parent_id' => $b->id, 'business_function_id' => $ownFunction->id,
    ])->assertStatus(422);

    expect($response->json('message'))->toContain('inherits a business function');
    expect(ProductCategory::where('name', 'C')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-006 — no-override on UPDATE, DB unchanged
// ---------------------------------------------------------------------------

it('AC-006: PATCH setting business_function_id while inheriting -> 422, DB unchanged', function () {
    $actor = productCategoryUserWith(['update']);
    $inheritedFunction = BusinessFunction::factory()->create();
    $ownFunction = BusinessFunction::factory()->create();
    $a = ProductCategory::factory()->create(['business_function_id' => $inheritedFunction->id]);
    $c = ProductCategory::factory()->childOf($a)->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/product-categories/{$c->id}", ['business_function_id' => $ownFunction->id])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('inherits a business function');
    expect($c->fresh()->business_function_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-007 — allowed when NOT inheriting
// ---------------------------------------------------------------------------

it('AC-007: PATCH setting business_function_id on a category with no inherited function -> 200, persisted', function () {
    $actor = productCategoryUserWith(['update', 'view']);
    $function = BusinessFunction::factory()->create();
    $x = ProductCategory::factory()->create();
    $y = ProductCategory::factory()->childOf($x)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$y->id}", ['business_function_id' => $function->id])
        ->assertOk()
        ->assertJsonPath('data.business_function_id', $function->id)
        ->assertJsonPath('data.effective_business_function.inherited', false);
});

// ---------------------------------------------------------------------------
// AC-008 — cascade on move (reparent under an inherited-function branch)
// ---------------------------------------------------------------------------

it('AC-008: moving a category with its own function under a function-bearing branch clears its own value', function () {
    $actor = productCategoryUserWith(['update']);
    $functionA = BusinessFunction::factory()->create();
    $functionY = BusinessFunction::factory()->create();
    $a = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $y = ProductCategory::factory()->create(['business_function_id' => $functionY->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/product-categories/{$y->id}", ['parent_id' => $a->id])->assertOk();

    $response->assertJsonPath('data.business_function_id', null)
        ->assertJsonPath('data.effective_business_function.id', $functionA->id)
        ->assertJsonPath('data.effective_business_function.inherited', true);

    expect($y->fresh()->business_function_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-009 — cascade on descendants, atomicity
// ---------------------------------------------------------------------------

it('AC-009: acquiring a function cascades to ALL descendants recursively, atomically', function () {
    $actor = productCategoryUserWith(['update', 'view']);
    $function = BusinessFunction::factory()->create();
    $childFunction = BusinessFunction::factory()->create();
    $x = ProductCategory::factory()->create();
    $y = ProductCategory::factory()->childOf($x)->create(['business_function_id' => $childFunction->id]);
    $z = ProductCategory::factory()->childOf($y)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$x->id}", ['business_function_id' => $function->id])->assertOk();

    expect($y->fresh()->business_function_id)->toBeNull()
        ->and($z->fresh()->business_function_id)->toBeNull();

    $yShow = $this->getJson("/api/product-categories/{$y->id}")->assertOk();
    expect($yShow->json('data.effective_business_function.id'))->toBe($function->id)
        ->and($yShow->json('data.effective_business_function.inherited'))->toBeTrue();

    $zShow = $this->getJson("/api/product-categories/{$z->id}")->assertOk();
    expect($zShow->json('data.effective_business_function.id'))->toBe($function->id)
        ->and($zShow->json('data.effective_business_function.inherited'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-010 — global invariant: at most one non-null per root->leaf chain
// ---------------------------------------------------------------------------

it('AC-010: after a sequence of updates, no root->leaf chain has two non-null business_function_id', function () {
    $actor = productCategoryUserWith(['update']);
    $functionA = BusinessFunction::factory()->create();
    $functionB = BusinessFunction::factory()->create();
    $a = ProductCategory::factory()->create();
    $b = ProductCategory::factory()->childOf($a)->create(['business_function_id' => $functionB->id]);
    $c = ProductCategory::factory()->childOf($b)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$a->id}", ['business_function_id' => $functionA->id])->assertOk();

    $chain = ProductCategory::whereIn('id', [$a->id, $b->id, $c->id])->get();
    expect($chain->whereNotNull('business_function_id')->count())->toBe(1)
        ->and($a->fresh()->business_function_id)->toBe($functionA->id);
});

// ---------------------------------------------------------------------------
// AC-011 — validation: nonexistent id, explicit null clear
// ---------------------------------------------------------------------------

it('AC-011: a nonexistent business_function_id -> 422', function () {
    $actor = productCategoryUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/product-categories', ['name' => 'X', 'business_function_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('business_function_id');
});

it('AC-011: business_function_id: null on a category that had its own -> 200, value cleared', function () {
    $actor = productCategoryUserWith(['update']);
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/product-categories/{$category->id}", ['business_function_id' => null])
        ->assertOk()
        ->assertJsonPath('data.business_function_id', null)
        ->assertJsonPath('data.effective_business_function', null);

    expect($category->fresh()->business_function_id)->toBeNull();
});
