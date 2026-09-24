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

it('AC-036/AC-053: the field catalogue is in the frozen order and omits completion_percentage, creator_id and is_blocked', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $keys = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'))->pluck('key')->all();

    // spec 0116, D-6: `is_blocked` left the 24-field catalogue — it is now
    // written only by the `block`/`unblock` domain actions, never by this
    // PATCH. spec 0121, D-1: `requires_validation` joins immediately after
    // `requires_closure_feedback`. spec 0120: `recurrence` joins LAST, after
    // `watcher_ids` (AC-053's own frozen order is additive at the tail for a
    // brand-new field with no natural neighbour among the existing 24),
    // bringing the frozen order to 25 fields. spec 0146, D-3:
    // `work_order_stage_id` joins right after `work_order_id` — unlike
    // `recurrence` it HAS a natural neighbour (a "Fase" only ever exists
    // under a `work_order_id`) — bringing the frozen order to 26 fields.
    // spec 0154 (REQUIREMENT CHANGED, count 26 -> 29): `is_private`,
    // `evidence` and `lead_id` join right before `recurrence`, the same
    // "additive at the tail" precedent `recurrence` itself set.
    expect($keys)->toBe([
        'title', 'task_status_id', 'description', 'registry_id', 'referent_id', 'parent_task_id',
        'task_type_id', 'task_priority_id', 'task_importance_id', 'task_category_id',
        'opportunity_id', 'work_order_id', 'work_order_stage_id', 'requester_id',
        'start_date', 'end_date', 'completion_date', 'start_time', 'end_time', 'estimated_minutes',
        'requires_closure_feedback', 'requires_validation', 'closure_feedback', 'assignee_ids', 'watcher_ids',
        'is_private', 'evidence', 'lead_id', 'recurrence',
    ])
        ->and($keys)->toHaveCount(29)
        ->and($keys)->not->toContain('completion_percentage')
        ->and($keys)->not->toContain('creator_id')
        ->and($keys)->not->toContain('is_blocked');
});

it('AC-053 / spec 0118 D-1: title, task_status_id, requester_id, assignee_ids and end_date are mandatory', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $fields = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'))->keyBy('key');

    expect($fields['title']['mandatory'])->toBeTrue()
        ->and($fields['task_status_id']['mandatory'])->toBeTrue()
        ->and($fields['requester_id']['mandatory'])->toBeTrue()
        ->and($fields['assignee_ids']['mandatory'])->toBeTrue()
        ->and($fields['end_date']['mandatory'])->toBeTrue()
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
// AC-003 (spec 0121) — requires_validation is boolean, optional, right after
// requires_closure_feedback
// ---------------------------------------------------------------------------

it('AC-003 (spec 0121): requires_validation is boolean, not mandatory, immediately after requires_closure_feedback', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $fields = collect($this->getJson('/api/meta/tasks')->assertOk()->json('data.fields'));
    $keys = $fields->pluck('key')->all();
    $byKey = $fields->keyBy('key');

    expect(array_search('requires_validation', $keys, true))
        ->toBe(array_search('requires_closure_feedback', $keys, true) + 1)
        ->and($byKey['requires_validation']['type'])->toBe('boolean')
        ->and($byKey['requires_validation']['mandatory'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-016 (spec 0118, REQUIREMENT CHANGED by spec 0154 D-10) — the
// create-context ceiling: task_status_id is now editable (a manual override
// is admitted, D-10) but never required, while the other three unlock
// ---------------------------------------------------------------------------

it('AC-016 (spec 0154, D-10): in create context, task_status_id is editable but never required, while requester_id/assignee_ids/end_date are required', function () {
    $actor = taskActorWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $permissions = $this->getJson('/api/meta/tasks')->assertOk()->json('permissions.fields');

    expect($permissions['task_status_id']['editable'])->toBeTrue()
        ->and($permissions['task_status_id']['required'])->toBeFalse()
        ->and($permissions['requester_id']['required'])->toBeTrue()
        ->and($permissions['assignee_ids']['required'])->toBeTrue()
        ->and($permissions['end_date']['required'])->toBeTrue();
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
