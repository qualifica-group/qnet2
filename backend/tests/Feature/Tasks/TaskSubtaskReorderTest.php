<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/tasks/{task}/subtasks/reorder (spec 0155, D-4/D-5, AC-006)
|--------------------------------------------------------------------------
|
| `ids` must be an exact permutation of the parent's own direct children:
| 200 with the resequenced `subtasks[]`, 403 without `update` on the parent,
| 422 on a mismatched set.
*/

if (! function_exists('taskActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

it('AC-006: an exact permutation resequences subtask_position and returns the ordered subtasks', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create();
    $a = Task::factory()->childOf($parent)->create(['subtask_position' => 0]);
    $b = Task::factory()->childOf($parent)->create(['subtask_position' => 1]);
    $c = Task::factory()->childOf($parent)->create(['subtask_position' => 2]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$c->id, $a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $c->id)
        ->assertJsonPath('data.1.id', $a->id)
        ->assertJsonPath('data.2.id', $b->id);

    $this->assertDatabaseHas('tasks', ['id' => $c->id, 'subtask_position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $a->id, 'subtask_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $b->id, 'subtask_position' => 2]);
});

it('AC-006: missing, foreign or duplicate ids are 422, nothing changes', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create();
    $a = Task::factory()->childOf($parent)->create();
    $b = Task::factory()->childOf($parent)->create();
    $foreign = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$a->id]])
        ->assertStatus(422);

    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$a->id, $foreign->id]])
        ->assertStatus(422);

    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$a->id, $a->id]])
        ->assertStatus(422);

    $this->assertDatabaseHas('tasks', ['id' => $b->id, 'subtask_position' => 0]);
});

it('AC-006: without update on the parent the reorder is 403', function () {
    $actor = taskActorWith(['view'], withViewAll: false);
    $parent = Task::factory()->create();
    $parent->watchers()->attach($actor->id);
    $child = Task::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$child->id]])
        ->assertForbidden();
});

// The reorder works on the children the actor can SEE (the only ids the panel
// knows): an invisible child keeps its slot and never appears in the answer.
it('AC-006: ids are the visible children only; an invisible child keeps its slot and is never returned', function () {
    $actor = taskActorWith(['view', 'update'], withViewAll: false);
    $parent = Task::factory()->forCreator($actor)->create();
    $a = Task::factory()->childOf($parent)->forCreator($actor)->create(['subtask_position' => 0, 'title' => 'Visibile A']);
    $hidden = Task::factory()->childOf($parent)->create(['subtask_position' => 1, 'title' => 'Nascosto']);
    $b = Task::factory()->childOf($parent)->forCreator($actor)->create(['subtask_position' => 2, 'title' => 'Visibile B']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$b->id, $a->id]])
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and($response->getContent())->not->toContain('Nascosto');

    $this->assertDatabaseHas('tasks', ['id' => $b->id, 'subtask_position' => 0]);
    $this->assertDatabaseHas('tasks', ['id' => $hidden->id, 'subtask_position' => 1]);
    $this->assertDatabaseHas('tasks', ['id' => $a->id, 'subtask_position' => 2]);

    // Naming the invisible child is a foreign id for this actor.
    $this->postJson("/api/tasks/{$parent->id}/subtasks/reorder", ['ids' => [$b->id, $hidden->id, $a->id]])
        ->assertUnprocessable();
});
