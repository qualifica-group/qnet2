<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Spec 0383 — an unassigned sub-task inherits its direct parent's assignees
|--------------------------------------------------------------------------
|
| A sub-task with no assignee of its own is visible and workable (the
| assignee row of TaskAbilityResolver) by the assignees of its DIRECT
| parent, so they can close it and then complete the parent (spec 0123 D-6).
*/

uses(RefreshDatabase::class);

function inheritedAssigneeActor(): User
{
    return taskActorWith(['viewAny', 'view', 'update', 'complete'], withViewAll: false);
}

/**
 * @return array{0: Task, 1: Task}
 */
function inheritedAssigneeFamily(User $parentAssignee, bool $privateChild = false): array
{
    $parent = Task::factory()->create(['title' => 'Parent']);
    $parent->assignees()->attach($parentAssignee->id);
    $childFactory = $privateChild ? Task::factory()->private() : Task::factory();

    return [$parent, $childFactory->childOf($parent)->create(['title' => 'Child'])];
}

it('AC-001: the parent assignee sees an unassigned direct sub-task in the detail and in the tree', function () {
    $actor = inheritedAssigneeActor();
    [$parent, $child] = inheritedAssigneeFamily($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$child->id}")->assertOk();

    $items = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50, 'treeParentId' => $parent->id,
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertOk()->json('items');

    expect(collect($items)->pluck('title')->all())->toBe(['Child']);
});

it('AC-002: the parent assignee completes the unassigned sub-task, then the parent', function () {
    $actor = inheritedAssigneeActor();
    [$parent, $child] = inheritedAssigneeFamily($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$child->id}")->assertJsonPath('permissions.actions.complete', true);
    $this->getJson("/api/tasks/{$parent->id}")->assertJsonPath('permissions.actions.complete', false);

    $this->postJson("/api/tasks/{$child->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertOk();

    $this->getJson("/api/tasks/{$parent->id}")->assertJsonPath('permissions.actions.complete', true);
    $this->postJson("/api/tasks/{$parent->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertOk();
});

it('AC-003: a sub-task with an assignee of its own is not inherited', function () {
    $actor = inheritedAssigneeActor();
    [, $child] = inheritedAssigneeFamily($actor);
    $child->assignees()->attach(User::factory()->create()->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$child->id}")->assertForbidden();
    $this->postJson("/api/tasks/{$child->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertForbidden();
});

it('AC-004: nothing is inherited from the grandparent', function () {
    $actor = inheritedAssigneeActor();
    [, $child] = inheritedAssigneeFamily($actor);
    $grandchild = Task::factory()->childOf($child)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$grandchild->id}")->assertForbidden();
});

it('AC-005: the inherited assignee only completes: no edit, no delete, validation when flagged', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update', 'delete', 'complete'], withViewAll: false);
    $parent = Task::factory()->create();
    $parent->assignees()->attach($actor->id);
    $child = Task::factory()->requiringValidation()->childOf($parent)->create(['title' => 'Child']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$child->id}")
        ->assertJsonPath('permissions.actions.complete_to_validation', true)
        ->assertJsonPath('permissions.actions.delete', false);

    $this->patchJson("/api/tasks/{$child->id}", ['title' => 'Renamed'])->assertForbidden();
    $this->deleteJson("/api/tasks/{$child->id}")->assertForbidden();
    expect($child->fresh()->title)->toBe('Child');
});

it('AC-006: the record verdict matches the query, with relations loaded and on a private sub-task', function () {
    $actor = inheritedAssigneeActor();
    [, $child] = inheritedAssigneeFamily($actor, privateChild: true);
    $stranger = inheritedAssigneeActor();

    $loaded = Task::query()->with(['assignees', 'watchers'])->findOrFail($child->id);

    expect(TaskVisibilityScope::isVisibleTo($actor, $loaded))->toBeTrue()
        ->and(TaskVisibilityScope::isVisibleTo($actor, Task::query()->findOrFail($child->id)))->toBeTrue()
        ->and(TaskVisibilityScope::scopeToActor(Task::query(), $actor)->whereKey($child->id)->exists())->toBeTrue()
        ->and(TaskVisibilityScope::isVisibleTo($stranger, $loaded))->toBeFalse()
        ->and(TaskVisibilityScope::scopeToActor(Task::query(), $stranger)->whereKey($child->id)->exists())->toBeFalse();
});
