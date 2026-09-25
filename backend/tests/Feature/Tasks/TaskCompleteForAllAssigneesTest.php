<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/tasks/{task}/complete, `for_all_assignees` (spec 0155, D-6,
| AC-008)
|--------------------------------------------------------------------------
*/

if (! function_exists('taskCompleteAllActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskCompleteAllActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.viewAll');

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        return $user;
    }
}

it('AC-008: for_all_assignees creates one identical time entry per assignee', function () {
    $actor = taskCompleteAllActorWith(['complete']);
    $second = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $second->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'time_entry' => validTimeEntryPayload(['minutes' => 45]),
        'for_all_assignees' => true,
    ])->assertOk();

    $this->assertDatabaseCount('time_entries', 2);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id, 'minutes' => 45]);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $second->id, 'minutes' => 45]);
});

it('AC-008: without for_all_assignees only the actor gets a time entry', function () {
    $actor = taskCompleteAllActorWith(['complete']);
    $second = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $second->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk();

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id]);
});

it('AC-008: for_all_assignees with zero assignees logs the entry for the actor alone', function () {
    $actor = taskCompleteAllActorWith(['complete']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'time_entry' => validTimeEntryPayload(),
        'for_all_assignees' => true,
    ])->assertOk();

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id]);
});

it('for_all_assignees defaults to false when absent', function () {
    $actor = taskCompleteAllActorWith(['complete']);
    $second = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $second->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk();

    expect(TimeEntry::where('task_id', $task->id)->count())->toBe(1);
});
