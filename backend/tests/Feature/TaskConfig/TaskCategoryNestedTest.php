<?php

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Nested task categories (spec 0154, D-1)
|--------------------------------------------------------------------------
|
| `task_categories.parent_id` is a self-referencing FK of unbounded depth,
| mirroring ProductCategory's own tree shape: no cycle, name unique per
| parent (roots included), color/icon never inherited, for-select projected
| depth-first with `meta.parent_id`/`meta.depth`.
*/

if (! function_exists('taskCategoryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskCategoryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("task-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — create with a parent, cycle guard
// ---------------------------------------------------------------------------

it('store: a category can be created with a parent_id, exposed as parent_id and parent{id,name}', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create', 'view']));
    $root = TaskCategory::factory()->create(['name' => 'Radice']);

    $response = $this->postJson('/api/task-categories', [
        'name' => 'Figlia', 'color' => 'blue', 'parent_id' => $root->id,
    ])->assertCreated();

    $response->assertJsonPath('data.parent_id', $root->id)
        ->assertJsonPath('data.parent.id', $root->id)
        ->assertJsonPath('data.parent.name', 'Radice');

    $this->assertDatabaseHas('task_categories', ['name' => 'Figlia', 'parent_id' => $root->id]);
});

it('store: parent_id null is a root category, exposed as parent: null', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create']));

    $this->postJson('/api/task-categories', ['name' => 'Radice sola', 'color' => 'blue'])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.parent', null);
});

it('store: a non-existent parent_id is 422', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create']));

    $this->postJson('/api/task-categories', ['name' => 'Orfana', 'color' => 'blue', 'parent_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('parent_id');
});

it('update: a category cannot become its own parent (422)', function () {
    Sanctum::actingAs(taskCategoryActorWith(['update']));
    $category = TaskCategory::factory()->create();

    $this->patchJson("/api/task-categories/{$category->id}", ['parent_id' => $category->id])
        ->assertStatus(422);

    expect($category->fresh()->parent_id)->toBeNull();
});

it('update: a category cannot be moved under one of its own descendants (422)', function () {
    Sanctum::actingAs(taskCategoryActorWith(['update']));
    $grandparent = TaskCategory::factory()->create(['name' => 'Nonna']);
    $parent = TaskCategory::factory()->childOf($grandparent)->create(['name' => 'Madre']);
    $child = TaskCategory::factory()->childOf($parent)->create(['name' => 'Figlia']);

    // Moving the grandparent under its own grandchild would create a cycle.
    $this->patchJson("/api/task-categories/{$grandparent->id}", ['parent_id' => $child->id])
        ->assertStatus(422);

    expect($grandparent->fresh()->parent_id)->toBeNull();
});

it('update: moving a category under an unrelated one is fine, and re-parenting to root (null) is fine', function () {
    Sanctum::actingAs(taskCategoryActorWith(['update']));
    $branchA = TaskCategory::factory()->create(['name' => 'Ramo A']);
    $branchB = TaskCategory::factory()->create(['name' => 'Ramo B']);
    $leaf = TaskCategory::factory()->childOf($branchA)->create(['name' => 'Foglia']);

    $this->patchJson("/api/task-categories/{$leaf->id}", ['parent_id' => $branchB->id])->assertOk();
    expect($leaf->fresh()->parent_id)->toBe($branchB->id);

    $this->patchJson("/api/task-categories/{$leaf->id}", ['parent_id' => null])->assertOk();
    expect($leaf->fresh()->parent_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// name unique PER PARENT (roots included)
// ---------------------------------------------------------------------------

it('store: the same name is allowed under two different parents, but not twice under the same one', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create']));
    $branchA = TaskCategory::factory()->create(['name' => 'Ramo A']);
    $branchB = TaskCategory::factory()->create(['name' => 'Ramo B']);

    $this->postJson('/api/task-categories', ['name' => 'Generica', 'color' => 'blue', 'parent_id' => $branchA->id])
        ->assertCreated();
    $this->postJson('/api/task-categories', ['name' => 'Generica', 'color' => 'blue', 'parent_id' => $branchB->id])
        ->assertCreated();

    $this->postJson('/api/task-categories', ['name' => 'Generica', 'color' => 'blue', 'parent_id' => $branchA->id])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('store: two root categories cannot share a name, both being NULL parent', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create']));
    TaskCategory::factory()->create(['name' => 'Radice unica', 'parent_id' => null]);

    $this->postJson('/api/task-categories', ['name' => 'Radice unica', 'color' => 'blue'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('update: renaming a category to a sibling name under the SAME parent is 422, under a DIFFERENT parent is fine', function () {
    Sanctum::actingAs(taskCategoryActorWith(['update']));
    $branchA = TaskCategory::factory()->create(['name' => 'Ramo A']);
    $branchB = TaskCategory::factory()->create(['name' => 'Ramo B']);
    TaskCategory::factory()->childOf($branchA)->create(['name' => 'Occupata']);
    $movable = TaskCategory::factory()->childOf($branchB)->create(['name' => 'Libera']);

    $this->patchJson("/api/task-categories/{$movable->id}", ['name' => 'Occupata'])->assertOk();

    $this->patchJson("/api/task-categories/{$movable->id}", ['parent_id' => $branchA->id])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// delete: a category with children cannot be removed (D-1, mirrors D-8b)
// ---------------------------------------------------------------------------

it('DELETE: a category with children is 409, naming the resource, and neither row is removed', function () {
    Sanctum::actingAs(taskCategoryActorWith(['delete']));
    $parent = TaskCategory::factory()->create();
    $child = TaskCategory::factory()->childOf($parent)->create();

    $this->deleteJson("/api/task-categories/{$parent->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This task category has child categories and cannot be deleted.');

    $this->assertDatabaseHas('task_categories', ['id' => $parent->id]);
    $this->assertDatabaseHas('task_categories', ['id' => $child->id]);
});

it('DELETE: a leaf category (no children, no task) is removed normally', function () {
    Sanctum::actingAs(taskCategoryActorWith(['delete']));
    $parent = TaskCategory::factory()->create();
    $leaf = TaskCategory::factory()->childOf($parent)->create();

    $this->deleteJson("/api/task-categories/{$leaf->id}")->assertNoContent();

    $this->assertDatabaseMissing('task_categories', ['id' => $leaf->id]);
    $this->assertDatabaseHas('task_categories', ['id' => $parent->id]);
});

it('DELETE: a category still used by a Task is 409 even without children', function () {
    Sanctum::actingAs(taskCategoryActorWith(['delete']));
    $category = TaskCategory::factory()->create();
    $task = Task::factory()->create(['task_category_id' => $category->id]);

    $this->deleteJson("/api/task-categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This task category is used by a task and cannot be deleted.');

    $this->assertDatabaseHas('task_categories', ['id' => $category->id]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

// ---------------------------------------------------------------------------
// for-select: depth-first tree order, meta.parent_id / meta.depth
// ---------------------------------------------------------------------------

it('for-select: items come depth-first (parent then its children), siblings by sort_order then name', function () {
    Sanctum::actingAs(taskCategoryActorWith([]));
    $branchB = TaskCategory::factory()->create(['name' => 'Ramo B', 'sort_order' => 20]);
    $branchA = TaskCategory::factory()->create(['name' => 'Ramo A', 'sort_order' => 10]);
    $child = TaskCategory::factory()->childOf($branchA)->create(['name' => 'Figlia', 'sort_order' => 10]);
    $grandchild = TaskCategory::factory()->childOf($branchA)->create(['name' => 'Nipote', 'sort_order' => 10]);
    $greatGrandchild = TaskCategory::factory()->childOf($grandchild)->create(['name' => 'Pronipote']);

    $ids = collect($this->getJson('/api/task-categories/for-select')->assertOk()->json('items'))->pluck('id')->all();

    // branchA (sort_order 10) before branchB (20); branchA's own children
    // immediately follow it (depth-first), ordered by sort_order then name
    // among themselves — "Figlia" before "Nipote" alphabetically at the
    // same sort_order; "Nipote"'s own child comes right after it, before the
    // walk returns to branchB.
    expect($ids)->toBe([$branchA->id, $child->id, $grandchild->id, $greatGrandchild->id, $branchB->id]);
});

it('for-select: meta carries parent_id (null for roots) and depth (0 for roots)', function () {
    Sanctum::actingAs(taskCategoryActorWith([]));
    $root = TaskCategory::factory()->create(['name' => 'Radice']);
    $child = TaskCategory::factory()->childOf($root)->create(['name' => 'Figlia']);
    $grandchild = TaskCategory::factory()->childOf($child)->create(['name' => 'Nipote']);

    $items = collect($this->getJson('/api/task-categories/for-select')->assertOk()->json('items'))->keyBy('id');

    expect($items[$root->id]['meta']['parent_id'])->toBeNull()
        ->and($items[$root->id]['meta']['depth'])->toBe(0)
        ->and($items[$child->id]['meta']['parent_id'])->toBe($root->id)
        ->and($items[$child->id]['meta']['depth'])->toBe(1)
        ->and($items[$grandchild->id]['meta']['parent_id'])->toBe($child->id)
        ->and($items[$grandchild->id]['meta']['depth'])->toBe(2);
});

it('for-select: a hydrated id (ids[]) outside the page still carries its correct depth', function () {
    Sanctum::actingAs(taskCategoryActorWith([]));
    $root = TaskCategory::factory()->create(['name' => 'Radice']);
    $child = TaskCategory::factory()->childOf($root)->create(['name' => 'Figlia disattivata', 'is_active' => false]);

    $items = collect($this->getJson("/api/task-categories/for-select?ids[]={$child->id}")->assertOk()->json('items'))
        ->keyBy('id');

    expect($items[$child->id]['meta']['depth'])->toBe(1)
        ->and($items[$child->id]['meta']['parent_id'])->toBe($root->id);
});

it('color and icon are never inherited from the parent', function () {
    Sanctum::actingAs(taskCategoryActorWith(['create', 'view']));
    $parent = TaskCategory::factory()->create(['color' => 'red', 'icon' => 'flag']);

    $response = $this->postJson('/api/task-categories', [
        'name' => 'Figlia senza eredita', 'color' => 'blue', 'parent_id' => $parent->id,
    ])->assertCreated();

    $response->assertJsonPath('data.color', 'blue')->assertJsonPath('data.icon', null);
});
