<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\ExportRun;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskClosed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| q-net form alignment (spec 0154): is_private/evidence/lead_id, born
| completed (D-6), notify flags (D-7), lookup defaults (D-8), manual initial
| status (D-10), commessa/opportunity/lead coherence (D-11).
|--------------------------------------------------------------------------
*/

if (! function_exists('formActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function formActorWith(array $abilities, bool $withViewAll = true): User
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

if (! function_exists('formTaskPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function formTaskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Attivita form q-net',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// D-2 / AC-003 — is_private narrows TaskVisibilityScope
// ---------------------------------------------------------------------------

it('AC-003: a private task is absent from the "visible" list for a non-member with tasks.viewAll', function () {
    $actor = formActorWith(['viewAny', 'view']);
    Task::factory()->private()->create(['title' => 'Segreto']);
    Sanctum::actingAs($actor);

    $items = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['assignment' => ['visible']],
    ])->assertOk()->json('items');

    expect(collect($items)->pluck('title')->all())->not->toContain('Segreto');
});

it('AC-003: a private task is 403 on GET detail for a non-member with tasks.viewAll', function () {
    $actor = formActorWith(['view']);
    $task = Task::factory()->private()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
});

it('AC-003: a private task is excluded from the export for a non-member with tasks.viewAll', function () {
    Storage::fake('local');
    $actor = formActorWith(['viewAny', 'view', 'export']);
    $task = Task::factory()->private()->create(['title' => 'Segreto']);
    Task::factory()->create(['title' => 'Pubblico']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();
    $csv = Storage::disk('local')->get($run->file_path);

    expect($csv)->toContain('Pubblico')->not->toContain('Segreto');
    expect(Task::query()->whereKey($task->id)->exists())->toBeTrue();
});

it('AC-003: a private task stays visible to its own watcher', function () {
    $actor = formActorWith(['view'], withViewAll: false);
    $task = Task::factory()->private()->create();
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();
});

it('AC-003: a private task stays visible to the super-admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));
    $task = Task::factory()->private()->create();
    Sanctum::actingAs($superAdmin);

    $this->getJson("/api/tasks/{$task->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// D-3 / AC-004 — evidence, sanitized and returned
// ---------------------------------------------------------------------------

it('AC-004: evidence is sanitized on create and returned in the resource', function () {
    $actor = formActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload([
        'evidence' => '<p>Consegnato</p><script>alert(1)</script>',
    ]))->assertCreated();

    expect($response->json('data.evidence'))->toBe('<p>Consegnato</p>')
        ->and($response->json('data.evidence'))->not->toContain('script');

    $this->assertDatabaseHas('tasks', ['id' => $response->json('data.id'), 'evidence' => '<p>Consegnato</p>']);
});

it('AC-004: evidence is sanitized on update', function () {
    $actor = formActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tasks/{$task->id}", ['evidence' => '<b>bold</b>not-allowed'])
        ->assertOk();

    expect($response->json('data.evidence'))->toContain('not-allowed')
        ->and($response->json('data.evidence'))->not->toContain('<b>');
});

// ---------------------------------------------------------------------------
// D-4 / AC-005 — lead_id must belong to the task's own registry
// ---------------------------------------------------------------------------

it('AC-005: lead_id of another registry is 422', function () {
    $actor = formActorWith(['create']);
    $registry = Registry::factory()->create();
    $otherLead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'registry_id' => $registry->id,
        'lead_id' => $otherLead->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('lead_id');
});

it('AC-005: lead_id of the same registry is 201', function () {
    $actor = formActorWith(['create', 'view']);
    $registry = Registry::factory()->create();
    $lead = Lead::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload([
        'registry_id' => $registry->id,
        'lead_id' => $lead->id,
    ]))->assertCreated();

    expect($response->json('data.lead.id'))->toBe($lead->id);
});

// ---------------------------------------------------------------------------
// D-6 / AC-006 — is_completed at creation
// ---------------------------------------------------------------------------

it('AC-006: is_completed creates a closed_positive task with completion_date today and one time entry', function () {
    $actor = formActorWith(['create', 'view']);
    $type = TaskType::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload([
        'task_type_id' => $type->id,
        'estimated_minutes' => 45,
        'is_completed' => true,
    ]))->assertCreated();

    $taskId = $response->json('data.id');

    expect($response->json('data.task_status.system_key'))->toBe(TaskStatusSystemKey::ClosedPositive->value)
        ->and($response->json('data.completion_date'))->toBe(now()->toDateString());

    $this->assertDatabaseCount('time_entries', 1);
    $entry = TimeEntry::query()->where('task_id', $taskId)->firstOrFail();
    expect($entry->user_id)->toBe($actor->id)->and($entry->minutes)->toBe(45);
});

it('AC-006: is_completed floors minutes at 1 when estimated_minutes is 0', function () {
    $actor = formActorWith(['create', 'view']);
    $type = TaskType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'task_type_id' => $type->id,
        'estimated_minutes' => 0,
        'is_completed' => true,
    ]))->assertCreated();

    expect(TimeEntry::query()->first()->minutes)->toBe(1);
});

it('AC-006: is_completed with requires_validation is 422', function () {
    $actor = formActorWith(['create']);
    TaskType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'requires_validation' => true,
        'is_completed' => true,
    ]))->assertStatus(422)->assertJsonValidationErrors('requires_validation');

    $this->assertDatabaseMissing('tasks', ['title' => 'Attivita form q-net']);
});

it('AC-006/AC-007: is_completed with notify_assigned_users false sends neither the assignment nor the closure notification', function () {
    $actor = formActorWith(['create', 'view']);
    TaskType::factory()->default()->create();
    Notification::fake();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'is_completed' => true,
        'notify_assigned_users' => false,
    ]))->assertCreated();

    Notification::assertNothingSent();
});

it('AC-006/AC-007: is_completed with notify_assigned_users true sends the closure notification, never the assignment one', function () {
    $actor = formActorWith(['create', 'view']);
    TaskType::factory()->default()->create();
    $requester = User::factory()->create();
    $firstAssignee = User::factory()->create();
    $secondAssignee = User::factory()->create();
    Notification::fake();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'requester_id' => $requester->id,
        'assignee_ids' => [$firstAssignee->id, $secondAssignee->id],
        'is_completed' => true,
    ]))->assertCreated();

    Notification::assertNotSentTo([$firstAssignee, $secondAssignee], TaskAssigned::class);
    Notification::assertSentTo($requester, TaskClosed::class);
});

// ---------------------------------------------------------------------------
// D-7 / AC-007 — notify_assigned_users / notify_new_assigned_users
// ---------------------------------------------------------------------------

it('AC-007: notify_assigned_users false sends no TaskAssigned/TaskObserver at creation', function () {
    $actor = formActorWith(['create', 'view']);
    Notification::fake();
    Sanctum::actingAs($actor);

    $watcher = User::factory()->create();

    $this->postJson('/api/tasks', formTaskPayload([
        'notify_assigned_users' => false,
        'watcher_ids' => [$watcher->id],
    ]))->assertCreated();

    Notification::assertNothingSent();
});

it('AC-007: notify_assigned_users defaults to true and sends TaskAssigned', function () {
    $actor = formActorWith(['create', 'view']);
    Notification::fake();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload())->assertCreated();

    Notification::assertSentTimes(TaskAssigned::class, 1);
});

it('AC-007: notify_new_assigned_users false suppresses notifications for a newly added assignee on PATCH', function () {
    $actor = formActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    $existing = User::factory()->create();
    $task->assignees()->attach($existing->id);
    $newAssignee = User::factory()->create();
    Notification::fake();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'assignee_ids' => [$existing->id, $newAssignee->id],
        'notify_new_assigned_users' => false,
    ])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// D-8 / AC-008 — default lookup rows
// ---------------------------------------------------------------------------

it('AC-008: creation without type/priority/importance uses the is_default rows', function () {
    $actor = formActorWith(['create', 'view']);
    $type = TaskType::factory()->default()->create();
    $priority = TaskPriority::factory()->default()->create();
    $importance = TaskImportance::factory()->default()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload())->assertCreated();

    expect($response->json('data.task_type.id'))->toBe($type->id)
        ->and($response->json('data.task_priority.id'))->toBe($priority->id)
        ->and($response->json('data.task_importance.id'))->toBe($importance->id);
});

it('AC-008: creation without any default row leaves the three lookups null', function () {
    $actor = formActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload())->assertCreated();

    expect($response->json('data.task_type_id'))->toBeNull()
        ->and($response->json('data.task_priority_id'))->toBeNull()
        ->and($response->json('data.task_importance_id'))->toBeNull();
});

// ---------------------------------------------------------------------------
// D-10 / AC-010 — manual initial status on create
// ---------------------------------------------------------------------------

it('AC-010: a manually chosen open-phase status outside open/assigned is honoured as chosen', function () {
    $actor = formActorWith(['create', 'view']);
    $pending = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload(['task_status_id' => $pending->id]))->assertCreated();

    expect($response->json('data.task_status.id'))->toBe($pending->id);
});

it('AC-010: a manually chosen "open" status is re-derived off the assignees, not honoured literally', function () {
    $actor = formActorWith(['create', 'view']);
    $open = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Open->value)->firstOrFail();
    $requester = User::factory()->create();
    $otherAssignee = User::factory()->create();
    Sanctum::actingAs($actor);

    // Requester is NOT the (sole) assignee: 0153 D-4 derives `assigned`, not `open`.
    $response = $this->postJson('/api/tasks', formTaskPayload([
        'requester_id' => $requester->id,
        'assignee_ids' => [$otherAssignee->id],
        'task_status_id' => $open->id,
    ]))->assertCreated();

    expect($response->json('data.task_status.system_key'))->toBe(TaskStatusSystemKey::Assigned->value);
});

it('AC-010: a manually chosen closing status on create is 422', function () {
    $actor = formActorWith(['create']);
    $closed = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload(['task_status_id' => $closed->id]))
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');
});

// ---------------------------------------------------------------------------
// D-11 / AC-011 — commessa/opportunity exclusivity and registry derivation
// ---------------------------------------------------------------------------

it('AC-011: work_order_id and opportunity_id together is 422', function () {
    $actor = formActorWith(['create']);
    $opportunity = Opportunity::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'opportunity_id' => $opportunity->id,
        'work_order_id' => $workOrder->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('work_order_id');
});

it('AC-011: choosing a commessa sets registry_id from its own chain when omitted', function () {
    $actor = formActorWith(['create', 'view']);
    $workOrder = WorkOrder::factory()->create();
    $registryId = $workOrder->quote?->opportunity?->registry_id;
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', formTaskPayload(['work_order_id' => $workOrder->id]))
        ->assertCreated();

    expect($response->json('data.registry_id'))->toBe($registryId);
});

it('AC-011: registry_id contradicting the commessa chain is 422', function () {
    $actor = formActorWith(['create']);
    $workOrder = WorkOrder::factory()->create();
    $otherRegistry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', formTaskPayload([
        'work_order_id' => $workOrder->id,
        'registry_id' => $otherRegistry->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('registry_id');
});
