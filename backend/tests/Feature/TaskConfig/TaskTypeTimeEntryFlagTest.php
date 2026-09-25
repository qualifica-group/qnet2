<?php

use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| task_types.requires_time_entry — CRUD (spec 0162, D-1, AC-007)
|--------------------------------------------------------------------------
|
| Authorization itself is unchanged (spec 0162, "autorizzazioni invariate"):
| this flag follows the SAME actorMayWrite() ceiling as every other editable
| field of the resource (App\Authorization\TaskTypesAuthorization), asserted
| generically by tests/Feature/TaskConfig/TaskConfigPermissionsTest.php's
| dataset. This file only covers the flag's own read/write shape.
*/

if (! function_exists('taskTypeActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskTypeActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("task-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-types.{$ability}");
        }

        return $user;
    }
}

it('AC-007: store without requires_time_entry defaults it to true', function () {
    Sanctum::actingAs(taskTypeActorWith(['create', 'view']));

    $response = $this->postJson('/api/task-types', ['name' => 'Manutenzione', 'color' => 'blue'])
        ->assertCreated()
        ->assertJsonPath('data.requires_time_entry', true);

    $this->assertDatabaseHas('task_types', ['id' => $response->json('data.id'), 'requires_time_entry' => true]);
});

it('AC-007: store with requires_time_entry false persists and rereads it', function () {
    Sanctum::actingAs(taskTypeActorWith(['create', 'view']));

    $response = $this->postJson('/api/task-types', [
        'name' => 'Sopralluogo', 'color' => 'green', 'requires_time_entry' => false,
    ])->assertCreated()->assertJsonPath('data.requires_time_entry', false);

    $taskTypeId = $response->json('data.id');
    $this->assertDatabaseHas('task_types', ['id' => $taskTypeId, 'requires_time_entry' => false]);

    $this->getJson("/api/task-types/{$taskTypeId}")
        ->assertOk()
        ->assertJsonPath('data.requires_time_entry', false);
});

it('AC-007: update toggles requires_time_entry both ways', function () {
    Sanctum::actingAs(taskTypeActorWith(['update', 'view']));
    $taskType = TaskType::factory()->create();

    $this->patchJson("/api/task-types/{$taskType->id}", ['requires_time_entry' => false])
        ->assertOk()
        ->assertJsonPath('data.requires_time_entry', false);
    $this->assertDatabaseHas('task_types', ['id' => $taskType->id, 'requires_time_entry' => false]);

    $this->patchJson("/api/task-types/{$taskType->id}", ['requires_time_entry' => true])
        ->assertOk()
        ->assertJsonPath('data.requires_time_entry', true);
    $this->assertDatabaseHas('task_types', ['id' => $taskType->id, 'requires_time_entry' => true]);
});

it('AC-007: update without requires_time_entry leaves it untouched (partial PATCH)', function () {
    Sanctum::actingAs(taskTypeActorWith(['update', 'view']));
    $taskType = TaskType::factory()->optionalTimeEntry()->create();

    $this->patchJson("/api/task-types/{$taskType->id}", ['name' => 'Rinominata'])
        ->assertOk()
        ->assertJsonPath('data.requires_time_entry', false);

    $this->assertDatabaseHas('task_types', ['id' => $taskType->id, 'requires_time_entry' => false, 'name' => 'Rinominata']);
});

it('AC-007: update is 403 without task-types.update, and requires_time_entry is untouched (authorization unchanged)', function () {
    Sanctum::actingAs(taskTypeActorWith(['view']));
    $taskType = TaskType::factory()->create(['requires_time_entry' => true]);

    $this->patchJson("/api/task-types/{$taskType->id}", ['requires_time_entry' => false])
        ->assertForbidden();

    $this->assertDatabaseHas('task_types', ['id' => $taskType->id, 'requires_time_entry' => true]);
});
