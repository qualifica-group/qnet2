<?php

use App\Enums\TaskStatusGroup;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| completion_date is server-owned (spec 0127, D-1/D-2/D-3)
|--------------------------------------------------------------------------
|
| Only the completion actions write it (TaskCompletionService); the client
| may never submit a value, and a manual close toward closed_negative leaves
| it empty.
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

if (! function_exists('completionDateTaskPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function completionDateTaskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Data di completamento',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-001/AC-002 — the client cannot write completion_date
// ---------------------------------------------------------------------------

it('AC-001: POST with a completion_date value is 422 and nothing is created', function () {
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', completionDateTaskPayload(['completion_date' => '2026-09-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('completion_date');

    $this->assertDatabaseMissing('tasks', ['title' => 'Data di completamento']);
});

it('AC-001: POST with completion_date null, or without the key, creates the task with no date', function (array $extra) {
    Sanctum::actingAs(taskActorWith(['create']));

    $this->postJson('/api/tasks', completionDateTaskPayload($extra))
        ->assertCreated()
        ->assertJsonPath('data.completion_date', null);
})->with([
    'null' => [['completion_date' => null]],
    'absent' => [[]],
]);

it('AC-002: PATCH with a completion_date value is 422 for the creator and for a super-admin, date unchanged', function () {
    $creator = taskActorWith(['view', 'update']);
    Role::findOrCreate('super-admin');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $task = Task::factory()->forCreator($creator)->create();

    foreach ([$creator, $superAdmin] as $actor) {
        Sanctum::actingAs($actor);

        $this->patchJson("/api/tasks/{$task->id}", ['completion_date' => '2026-09-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('completion_date');
    }

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completion_date' => null]);
});

// ---------------------------------------------------------------------------
// AC-003 — a manual negative close carries no completion date
// ---------------------------------------------------------------------------

it('AC-003: PATCHing an open task toward closed_negative leaves completion_date null', function () {
    $creator = taskActorWith(['view', 'update']);
    $closedNegative = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    $task = Task::factory()->forCreator($creator)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closedNegative->id])
        ->assertOk()
        ->assertJsonPath('data.completion_date', null);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $closedNegative->id, 'completion_date' => null]);
});

// ---------------------------------------------------------------------------
// AC-005 — the field is never editable
// ---------------------------------------------------------------------------

it('AC-005: permissions.fields.completion_date is not editable for the creator nor for a super-admin', function () {
    $creator = taskActorWith(['view', 'update']);
    Role::findOrCreate('super-admin');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $task = Task::factory()->forCreator($creator)->create();

    foreach ([$creator, $superAdmin] as $actor) {
        Sanctum::actingAs($actor);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('permissions.fields.completion_date.editable', false);
    }
});
