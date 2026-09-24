<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| TaskResource.subtasks[] carries `position` and `permissions.actions`
| (spec 0155, D-5)
|--------------------------------------------------------------------------
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

it('D-5: GET exposes each subtask\'s own position and permissions.actions', function () {
    $actor = taskActorWith(['view', 'delete']);
    $parent = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->childOf($parent)->forCreator($actor)->create(['subtask_position' => 3]);
    $child->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.subtasks.0.id', $child->id)
        ->assertJsonPath('data.subtasks.0.position', 3)
        ->assertJsonPath('data.subtasks.0.permissions.actions.delete', true);
});

it('D-5: a subtask\'s own permissions.actions.delete is false for an actor without the mandate on it', function () {
    $actor = taskActorWith(['view']);
    $parent = Task::factory()->forCreator($actor)->create();
    // The child belongs to someone else entirely: $actor holds no role on IT.
    $child = Task::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.subtasks.0.permissions.actions.delete', false);
});

it('the subtasks list is ordered by subtask_position then id', function () {
    $actor = taskActorWith(['view']);
    $parent = Task::factory()->forCreator($actor)->create();
    $second = Task::factory()->childOf($parent)->create(['subtask_position' => 1]);
    $first = Task::factory()->childOf($parent)->create(['subtask_position' => 0]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.subtasks.0.id', $first->id)
        ->assertJsonPath('data.subtasks.1.id', $second->id);
});

// No N+1: the per-child permissions (incl. the open-subtasks count behind
// `complete`) must not add queries per sub-task.
it('D-5: GET detail runs the same number of queries with 1 or 5 subtasks', function () {
    $actor = taskActorWith(['view', 'update', 'delete', 'complete']);
    Sanctum::actingAs($actor);

    $queriesFor = function (int $children) use ($actor): int {
        $parent = Task::factory()->forCreator($actor)->create();
        foreach (range(1, $children) as $position) {
            $child = Task::factory()->childOf($parent)->forCreator($actor)->create(['subtask_position' => $position]);
            $child->assignees()->attach($actor->id);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson("/api/tasks/{$parent->id}")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // Warm-up: the first request also loads the actor's roles/permissions and
    // the custom-field catalogue, cached afterwards for both measured runs.
    $queriesFor(1);

    expect($queriesFor(5))->toBe($queriesFor(1));
});
