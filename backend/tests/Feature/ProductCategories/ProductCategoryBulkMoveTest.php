<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Bulk reparenting — POST /api/product-categories/bulk-move (spec 0063).
 * All-or-nothing: every rejection test also asserts that NOTHING moved.
 */
if (! function_exists('bulkMoveUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function bulkMoveUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
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
// Happy paths
// ---------------------------------------------------------------------------

it('AC-001 moves every selected category under the destination', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $destination = ProductCategory::factory()->create(['name' => 'Destination']);
    $first = ProductCategory::factory()->create();
    $second = ProductCategory::factory()->create();
    $third = ProductCategory::factory()->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$first->id, $second->id, $third->id],
        'parent_id' => $destination->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.moved', 3);

    expect($first->fresh()->parent_id)->toBe($destination->id)
        ->and($second->fresh()->parent_id)->toBe($destination->id)
        ->and($third->fresh()->parent_id)->toBe($destination->id);
});

it('AC-002 moves the selection to the root when parent_id is null', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $root = ProductCategory::factory()->create();
    $first = ProductCategory::factory()->childOf($root)->create();
    $second = ProductCategory::factory()->childOf($root)->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$first->id, $second->id],
        'parent_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.moved', 2);

    expect($first->fresh()->parent_id)->toBeNull()
        ->and($second->fresh()->parent_id)->toBeNull();
});

it('AC-003 does not count a category already sitting under the destination', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $destination = ProductCategory::factory()->create();
    $alreadyThere = ProductCategory::factory()->childOf($destination)->create();
    $elsewhere = ProductCategory::factory()->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$alreadyThere->id, $elsewhere->id],
        'parent_id' => $destination->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.moved', 1);

    expect($alreadyThere->fresh()->parent_id)->toBe($destination->id)
        ->and($elsewhere->fresh()->parent_id)->toBe($destination->id);
});

// ---------------------------------------------------------------------------
// Conflicts — every one of them writes nothing (decision D-3)
// ---------------------------------------------------------------------------

it('AC-004 rejects a destination that is part of the selection', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $destination = ProductCategory::factory()->create(['name' => 'Destination']);
    $other = ProductCategory::factory()->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$destination->id, $other->id],
        'parent_id' => $destination->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.reason', 'self_parent')
        ->assertJsonPath('errors.conflicts.0.name', 'Destination');

    expect($other->fresh()->parent_id)->toBeNull();
});

it('AC-005 rejects a nested selection and moves nothing', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $destination = ProductCategory::factory()->create();
    $ancestor = ProductCategory::factory()->create(['name' => 'Ancestor']);
    $descendant = ProductCategory::factory()->childOf($ancestor)->create(['name' => 'Descendant']);

    $response = $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$ancestor->id, $descendant->id],
        'parent_id' => $destination->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.reason', 'nested_selection');

    expect(collect($response->json('errors.conflicts'))->pluck('name')->all())->toBe(['Descendant']);
    expect($ancestor->fresh()->parent_id)->toBeNull()
        ->and($descendant->fresh()->parent_id)->toBe($ancestor->id);
});

it('AC-006 rejects a destination that descends from a selected category', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $moving = ProductCategory::factory()->create(['name' => 'Moving']);
    $child = ProductCategory::factory()->childOf($moving)->create();
    $destination = ProductCategory::factory()->childOf($child)->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$moving->id],
        'parent_id' => $destination->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.reason', 'cycle')
        ->assertJsonPath('errors.conflicts.0.name', 'Moving');

    expect($moving->fresh()->parent_id)->toBeNull();
});

it('AC-007 rejects a category whose own business function would be overridden', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $destinationFunction = BusinessFunction::factory()->create(['name' => 'Destination function']);
    $ownFunction = BusinessFunction::factory()->create();
    $destination = ProductCategory::factory()->create(['business_function_id' => $destinationFunction->id]);
    $moving = ProductCategory::factory()->create([
        'name' => 'Moving',
        'business_function_id' => $ownFunction->id,
    ]);

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$moving->id],
        'parent_id' => $destination->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.reason', 'business_function_conflict')
        ->assertJsonPath('errors.conflicts.0.name', 'Moving');

    expect($moving->fresh()->parent_id)->toBeNull()
        ->and($moving->fresh()->business_function_id)->toBe($ownFunction->id);
});

it('AC-008 writes nothing when a single row of an otherwise valid batch conflicts', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $function = BusinessFunction::factory()->create();
    $destination = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $valid = ProductCategory::factory()->create();
    $alsoValid = ProductCategory::factory()->create();
    $conflicting = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$valid->id, $alsoValid->id, $conflicting->id],
        'parent_id' => $destination->id,
    ])->assertStatus(422);

    expect($valid->fresh()->parent_id)->toBeNull()
        ->and($alsoValid->fresh()->parent_id)->toBeNull()
        ->and($conflicting->fresh()->parent_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Authorization + side effects
// ---------------------------------------------------------------------------

it('AC-009 returns 403 without product-categories.update and moves nothing', function () {
    Sanctum::actingAs(bulkMoveUserWith(['view']));
    $destination = ProductCategory::factory()->create();
    $target = ProductCategory::factory()->create();

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$target->id],
        'parent_id' => $destination->id,
    ])->assertForbidden();

    expect($target->fresh()->parent_id)->toBeNull();
});

it('AC-010 cascades the business function to the moved subtree', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $ownFunction = BusinessFunction::factory()->create();
    $descendantFunction = BusinessFunction::factory()->create();
    $destination = ProductCategory::factory()->create();
    $moving = ProductCategory::factory()->create(['business_function_id' => $ownFunction->id]);
    $descendant = ProductCategory::factory()->childOf($moving)->create([
        'business_function_id' => $descendantFunction->id,
    ]);

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$moving->id],
        'parent_id' => $destination->id,
    ])->assertOk();

    expect($moving->fresh()->business_function_id)->toBe($ownFunction->id)
        ->and($descendant->fresh()->business_function_id)->toBeNull();
});

it('rejects an empty selection and an unknown destination', function () {
    Sanctum::actingAs(bulkMoveUserWith(['update']));
    $target = ProductCategory::factory()->create();

    $this->postJson('/api/product-categories/bulk-move', ['category_ids' => [], 'parent_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_ids');

    $this->postJson('/api/product-categories/bulk-move', [
        'category_ids' => [$target->id],
        'parent_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');
});
