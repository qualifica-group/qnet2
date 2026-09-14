<?php

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Time entry CRUD (spec 0122, MT-B2, AC-001..AC-009)
|--------------------------------------------------------------------------
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * An actor holding $abilities on `time-entries`. Deliberately NOT
     * auto-granting `manageAll` (unlike taskActorWith's `viewAll`): AC-007/
     * AC-008 are precisely about the difference between holding it and not.
     *
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('timeEntryPayload')) {
    /**
     * The four create-mandatory fields (data_contract POST): `date`,
     * `title`, `task_type_id` and `minutes`.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function timeEntryPayload(array $overrides = []): array
    {
        return [
            'date' => '2026-09-14',
            'title' => 'Chiamata cliente',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 90,
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-001 — create, shape and permissions
// ---------------------------------------------------------------------------

it('AC-001: POST with date/title/task_type_id/minutes=90 is 201 with the TimeEntry shape', function () {
    $actor = timeEntryActorWith(['create', 'view', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload(['minutes' => 90]))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.minutes', 90)
        ->assertJsonPath('data.user.id', $actor->id)
        ->assertJsonPath('data.permissions.update', true)
        ->assertJsonPath('data.permissions.delete', true);

    $this->assertDatabaseHas('time_entries', ['id' => $response->json('data.id'), 'user_id' => $actor->id]);
});

// ---------------------------------------------------------------------------
// AC-002 — start_time/end_time: both or neither, end strictly after start
// ---------------------------------------------------------------------------

it('AC-002: end_time before start_time is 422 on end_time, no row created', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['start_time' => '09:00', 'end_time' => '08:30']))
        ->assertStatus(422)->assertJsonValidationErrors('end_time');

    $this->assertDatabaseMissing('time_entries', ['user_id' => $actor->id]);
});

it('AC-002: end_time equal to start_time is 422 on end_time (strictly after)', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['start_time' => '09:00', 'end_time' => '09:00']))
        ->assertStatus(422)->assertJsonValidationErrors('end_time');
});

it('AC-002: only start_time submitted is 422 on end_time', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['start_time' => '09:00']))
        ->assertStatus(422)->assertJsonValidationErrors('end_time');
});

it('AC-002: only end_time submitted is 422 on start_time', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['end_time' => '10:00']))
        ->assertStatus(422)->assertJsonValidationErrors('start_time');
});

// ---------------------------------------------------------------------------
// AC-003 — minutes 1..1440
// ---------------------------------------------------------------------------

it('AC-003: minutes 0 is 422', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['minutes' => 0]))
        ->assertStatus(422)->assertJsonValidationErrors('minutes');
});

it('AC-003: minutes 1441 is 422', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['minutes' => 1441]))
        ->assertStatus(422)->assertJsonValidationErrors('minutes');
});

it('AC-003: minutes absent is 422', function () {
    $actor = timeEntryActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = timeEntryPayload();
    unset($payload['minutes']);

    $this->postJson('/api/time-entries', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('minutes');
});

// ---------------------------------------------------------------------------
// AC-004/AC-005 — D-5 link rule: task_id overrides, invisible task is 422
// ---------------------------------------------------------------------------

it('AC-004: task_id overrides the submitted title/registry_id with the Task\'s own links', function () {
    $actor = timeEntryActorWith(['create']);
    $registry = Registry::factory()->create();
    $otherRegistry = Registry::factory()->create();
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $task = Task::factory()->forCreator($actor)->create([
        'title' => 'X',
        'registry_id' => $registry->id,
        'opportunity_id' => $opportunity->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload([
        'task_id' => $task->id,
        'title' => 'Titolo diverso',
        'registry_id' => $otherRegistry->id,
    ]))->assertCreated();

    expect($response->json('data.title'))->toBe('X')
        ->and($response->json('data.registry.id'))->toBe($registry->id)
        ->and($response->json('data.opportunity.id'))->toBe($opportunity->id)
        ->and($response->json('data.task.id'))->toBe($task->id);

    $this->assertDatabaseHas('time_entries', [
        'id' => $response->json('data.id'),
        'title' => 'X',
        'registry_id' => $registry->id,
    ]);
});

it('AC-005: task_id not visible to the actor is 422 on task_id', function () {
    $actor = timeEntryActorWith(['create']);
    $task = Task::factory()->create(); // unrelated creator/assignees; actor lacks tasks.viewAll
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['task_id' => $task->id]))
        ->assertStatus(422)->assertJsonValidationErrors('task_id');

    $this->assertDatabaseMissing('time_entries', ['task_id' => $task->id]);
});

// ---------------------------------------------------------------------------
// AC-006 — opportunity/work_order exclusivity and client coherence
// ---------------------------------------------------------------------------

it('AC-006: opportunity_id and work_order_id together is 422 on work_order_id', function () {
    $actor = timeEntryActorWith(['create']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload([
        'opportunity_id' => $opportunity->id,
        'work_order_id' => WorkOrder::factory()->create()->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('work_order_id');
});

it('AC-006: registry_id different from the opportunity\'s client is 422 on registry_id', function () {
    $actor = timeEntryActorWith(['create']);
    $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);
    $foreignRegistry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload([
        'opportunity_id' => $opportunity->id,
        'registry_id' => $foreignRegistry->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('registry_id');
});

it('AC-006: only opportunity_id sets registry_id to the opportunity\'s own client', function () {
    $actor = timeEntryActorWith(['create']);
    $registry = Registry::factory()->create();
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload(['opportunity_id' => $opportunity->id]))
        ->assertCreated();

    expect($response->json('data.registry.id'))->toBe($registry->id);
});

// ---------------------------------------------------------------------------
// AC-007 — user_id, manageAll
// ---------------------------------------------------------------------------

it('AC-007: POST with user_id of someone else, without manageAll, is 403', function () {
    $actor = timeEntryActorWith(['create']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/time-entries', timeEntryPayload(['user_id' => $other->id]))
        ->assertForbidden();
});

it('AC-007: POST with user_id of someone else, with manageAll, is 201 and belongs to that user', function () {
    $actor = timeEntryActorWith(['create', 'manageAll']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/time-entries', timeEntryPayload(['user_id' => $other->id]))
        ->assertCreated();

    expect($response->json('data.user.id'))->toBe($other->id);
    $this->assertDatabaseHas('time_entries', ['id' => $response->json('data.id'), 'user_id' => $other->id]);
});

// ---------------------------------------------------------------------------
// AC-008 — GET/PUT/DELETE on someone else's entry
// ---------------------------------------------------------------------------

it('AC-008: GET/PUT/DELETE on another user\'s entry, without manageAll, is 403', function () {
    $owner = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($owner)->create();
    $actor = timeEntryActorWith(['view', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/time-entries/{$entry->id}")->assertForbidden();
    $this->putJson("/api/time-entries/{$entry->id}", timeEntryPayload())->assertForbidden();
    $this->deleteJson("/api/time-entries/{$entry->id}")->assertForbidden();
});

it('AC-008: GET/PUT/DELETE on another user\'s entry, with manageAll, is 200', function () {
    $owner = User::factory()->create();
    $entry = TimeEntry::factory()->forUser($owner)->create();
    $actor = timeEntryActorWith(['view', 'update', 'delete', 'manageAll']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/time-entries/{$entry->id}")->assertOk();

    $updated = TimeEntry::factory()->forUser($owner)->create();
    $this->putJson("/api/time-entries/{$updated->id}", timeEntryPayload())->assertOk();

    $deleted = TimeEntry::factory()->forUser($owner)->create();
    $this->deleteJson("/api/time-entries/{$deleted->id}")->assertOk();
    $this->assertDatabaseMissing('time_entries', ['id' => $deleted->id]);
});

// ---------------------------------------------------------------------------
// AC-009 — owner PUT/DELETE, user_id prohibited on PUT
// ---------------------------------------------------------------------------

it('AC-009: owner PUT changes minutes, then DELETE removes the row', function () {
    $actor = timeEntryActorWith(['view', 'update', 'delete']);
    $entry = TimeEntry::factory()->forUser($actor)->create(['minutes' => 30]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/time-entries/{$entry->id}", timeEntryPayload(['minutes' => 45]))
        ->assertOk()
        ->assertJsonPath('data.minutes', 45);

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'minutes' => 45]);

    $this->deleteJson("/api/time-entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
});

it('AC-009: user_id in the PUT body is 422, nothing changes', function () {
    $actor = timeEntryActorWith(['view', 'update']);
    $entry = TimeEntry::factory()->forUser($actor)->create(['minutes' => 30]);
    Sanctum::actingAs($actor);

    $this->putJson("/api/time-entries/{$entry->id}", timeEntryPayload(['user_id' => $actor->id]))
        ->assertStatus(422)->assertJsonValidationErrors('user_id');

    $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'minutes' => 30]);
});
