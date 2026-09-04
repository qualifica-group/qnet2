<?php

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Grid config and field meta of `tasks` (spec 0101, AC-052..AC-054)
|--------------------------------------------------------------------------
|
| Split out of TaskPermissionsTest to keep both files under the 300-line soft
| limit (engineering.md §6), along the same seam the repo already draws
| between WorkOrderSecurityTest and WorkOrderMetaTest.
*/

if (! function_exists('taskActorWith')) {
    /**
     * An actor holding $abilities on `tasks`. `viewAll` is granted on top by
     * default so a 403 in a suite that is NOT about the membership scoping
     * always means "missing resource permission" — the separation
     * WorkOrderSecurityTest/WorkOrderVisibilityTest already draw. Pass
     * `withViewAll: false` to exercise the scope itself.
     *
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
     *
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
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

// ---------------------------------------------------------------------------
// AC-052 — the grid column config is gated by tasks.viewAny
// ---------------------------------------------------------------------------

it('AC-052: GET /api/tables/tasks/columns is 403 without tasks.viewAny', function () {
    $actor = taskActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/tasks/columns')->assertForbidden();
});

it('AC-052: GET /api/tables/tasks/columns returns the data_contract columns', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.columns'))->pluck('id');

    foreach ([
        'id', 'title', 'registry', 'task_type', 'task_status', 'task_priority', 'task_importance',
        'task_category', 'start_date', 'end_date', 'requester', 'assignees', 'completion_percentage',
        'estimated_minutes', 'is_blocked', 'opportunity', 'work_order', 'creator',
    ] as $column) {
        expect($ids)->toContain($column);
    }
});

// ---------------------------------------------------------------------------
// AC-053 — the meta field catalogue, in the frozen order
// ---------------------------------------------------------------------------

it('AC-053: GET /api/meta/tasks is 403 without tasks.viewAny', function () {
    $actor = taskActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/tasks')->assertForbidden();
});

it('AC-053: the field catalogue is in the frozen order and omits completion_percentage and creator_id', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $keys = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'))->pluck('key')->all();

    expect($keys)->toBe([
        'title', 'task_status_id', 'description', 'registry_id', 'referent_id', 'parent_task_id',
        'task_type_id', 'task_priority_id', 'task_importance_id', 'task_category_id',
        'opportunity_id', 'work_order_id', 'requester_id',
        'start_date', 'end_date', 'completion_date', 'start_time', 'end_time', 'estimated_minutes',
        'is_blocked', 'requires_closure_feedback', 'closure_feedback', 'assignee_ids', 'watcher_ids',
    ])
        ->and($keys)->not->toContain('completion_percentage')
        ->and($keys)->not->toContain('creator_id');
});

it('AC-053: only title and task_status_id are mandatory', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $fields = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'))->keyBy('key');

    expect($fields['title']['mandatory'])->toBeTrue()
        ->and($fields['task_status_id']['mandatory'])->toBeTrue()
        ->and($fields['description']['mandatory'])->toBeFalse()
        ->and($fields['closure_feedback']['mandatory'])->toBeFalse()
        // D-11: FieldDefinition has no `time` type, so the two clock fields
        // are declared `text` with server-side date_format validation.
        ->and($fields['start_time']['type'])->toBe('text')
        ->and($fields['end_time']['type'])->toBe('text')
        ->and($fields['assignee_ids']['type'])->toBe('multiselect')
        ->and($fields['watcher_ids']['type'])->toBe('multiselect');
});

// ---------------------------------------------------------------------------
// AC-054 — a restrictive role_field_permissions row is enforced on write
// ---------------------------------------------------------------------------

it('AC-054: a role denying estimated_minutes makes it readonly in the meta and 422 on write', function () {
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    $role = Role::create(['name' => 'task-estimate-locked']);
    $role->givePermissionTo(['tasks.viewAny', 'tasks.view', 'tasks.update', 'tasks.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'tasks',
        'field' => 'estimated_minutes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    $task = Task::factory()->create(['estimated_minutes' => 30]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/tasks')
        ->assertOk()
        ->assertJsonPath('permissions.fields.estimated_minutes.editable', false)
        ->assertJsonPath('permissions.fields.estimated_minutes.readonly', true);

    $this->patchJson("/api/tasks/{$task->id}", ['estimated_minutes' => 999])
        ->assertStatus(422)->assertJsonValidationErrors('estimated_minutes');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'estimated_minutes' => 30]);
});
