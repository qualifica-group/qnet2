<?php

use App\Enums\MigrationStatus;
use App\Enums\TaskStatusSystemKey;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// seedMigrationsConfig/migrationsSuperAdminActor live in
// tests/Helpers/MigrationHelpers.php; the legacy task fixtures
// (legacyTaskRecord, runLegacyTasksImport, ...) in
// tests/Helpers/TaskMigrationHelpers.php.

beforeEach(function () {
    seedMigrationsConfig();
    $this->creator = legacyTaskCreator();
});

function taskStatusIdByKey(TaskStatusSystemKey $key): int
{
    return (int) TaskStatus::query()->where('system_key', $key->value)->value('id');
}

/**
 * A migrated commessa whose offer belongs to a migrated client.
 *
 * @return array{0: WorkOrder, 1: Registry}
 */
function migratedWorkOrder(int $oldId = 17): array
{
    $registry = Registry::factory()->create(['old_id' => 700]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->forceFill(['old_id' => $oldId])->save();

    return [$workOrder, $registry];
}

it('AC-001 imports a complete legacy task with its anchors, without log or notification', function () {
    Notification::fake();
    $creator = User::factory()->create(['old_id' => 61]);
    $type = TaskType::factory()->create(['name' => 'Riunione']);
    $category = TaskCategory::factory()->create(['name' => 'Supporto']);
    $priority = TaskPriority::factory()->create(['name' => 'Alta']);
    $importance = TaskImportance::factory()->create(['name' => 'Massima']);
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyTasksImport([legacyTaskRecord([
        'description' => '<p>Hello <script>alert(1)</script><strong>world</strong></p>',
        'type_name' => 'Riunione',
        'category_name' => 'Supporto',
        'priority_name' => 'Alta',
        'importance_name' => 'Massima',
        'creator_user_id' => 61,
        'start_date' => '2024-05-01',
        'end_date' => '2024-05-10',
        'completion_date' => '2024-05-09',
        'start_time' => '09:30',
        'end_time' => '11:00',
        'estimated_minutes' => 90,
        'is_blocked' => true,
        'requires_closure_feedback' => true,
        'requires_validation' => true,
        'closure_feedback' => 'Done well',
    ])], $actor);

    $task = Task::query()->where('old_id', 500)->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($task->title)->toBe('Legacy task')
        ->and($task->description)->toBe('<p>Hello <strong>world</strong></p>')
        ->and($task->task_type_id)->toBe($type->id)
        ->and($task->task_category_id)->toBe($category->id)
        ->and($task->task_priority_id)->toBe($priority->id)
        ->and($task->task_importance_id)->toBe($importance->id)
        ->and($task->creator_id)->toBe($creator->id)
        ->and($task->start_date->toDateString())->toBe('2024-05-01')
        ->and($task->end_date->toDateString())->toBe('2024-05-10')
        ->and($task->completion_date->toDateString())->toBe('2024-05-09')
        ->and(substr((string) $task->start_time, 0, 5))->toBe('09:30')
        ->and(substr((string) $task->end_time, 0, 5))->toBe('11:00')
        ->and($task->estimated_minutes)->toBe(90)
        ->and($task->is_blocked)->toBeTrue()
        ->and($task->requires_closure_feedback)->toBeTrue()
        ->and($task->requires_validation)->toBeTrue()
        ->and($task->closure_feedback)->toBe('Done well')
        ->and($task->is_private)->toBeFalse()
        ->and($task->created_at->toDateTimeString())->toBe('2024-03-01 08:00:00')
        ->and($task->updated_at->toDateTimeString())->toBe('2024-03-02 08:00:00')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-002 skips an already imported task on a second run', function () {
    runLegacyTasksImport([legacyTaskRecord()]);
    $second = runLegacyTasksImport([legacyTaskRecord(['title' => 'Changed'])]);

    expect($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0)
        ->and(Task::query()->where('old_id', 500)->count())->toBe(1)
        ->and(Task::query()->where('old_id', 500)->value('title'))->toBe('Legacy task');
});

it('AC-003 resolves the status by system key, by name (case and spaces) or derives it', function () {
    TaskStatus::factory()->create(['name' => 'In attesa controparte']);
    $custom = TaskStatus::query()->where('name', 'In attesa controparte')->value('id');
    User::factory()->create(['old_id' => 62]);

    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 501, 'status_system_key' => 'closed_positive']),
        legacyTaskRecord(['id' => 502, 'status_name' => '  in ATTESA controparte ']),
        legacyTaskRecord(['id' => 503, 'status_name' => 'Nonexistent', 'assignee_user_ids' => [62]]),
        legacyTaskRecord(['id' => 504]),
        legacyTaskRecord(['id' => 505, 'assignee_user_ids' => [62]]),
    ]);

    $statusOf = fn (int $oldId): int => Task::query()->where('old_id', $oldId)->value('task_status_id');

    expect($statusOf(501))->toBe(taskStatusIdByKey(TaskStatusSystemKey::ClosedPositive))
        ->and($statusOf(502))->toBe($custom)
        ->and($statusOf(503))->toBe(taskStatusIdByKey(TaskStatusSystemKey::Assigned))
        ->and($statusOf(504))->toBe(taskStatusIdByKey(TaskStatusSystemKey::Assigned))
        ->and($statusOf(505))->toBe(taskStatusIdByKey(TaskStatusSystemKey::Assigned))
        ->and(legacyTaskWarnings($run))->toBe(['Unknown task status "Nonexistent"; the status was derived.']);
});

it('AC-004 falls back to the defaults for unknown lookups and leaves an unknown category empty', function () {
    $type = TaskType::factory()->create(['name' => 'Default type', 'is_default' => true]);
    $priority = TaskPriority::factory()->create(['name' => 'Default priority', 'is_default' => true]);
    $importance = TaskImportance::factory()->create(['name' => 'Default importance', 'is_default' => true]);

    $run = runLegacyTasksImport([legacyTaskRecord([
        'type_name' => 'Nope', 'priority_name' => 'Nope', 'importance_name' => 'Nope', 'category_name' => 'Nope',
    ])]);

    $task = Task::query()->where('old_id', 500)->sole();

    expect($task->task_type_id)->toBe($type->id)
        ->and($task->task_priority_id)->toBe($priority->id)
        ->and($task->task_importance_id)->toBe($importance->id)
        ->and($task->task_category_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe([
            'Unknown task type "Nope"; the default was used.',
            'Unknown task priority "Nope"; the default was used.',
            'Unknown task importance "Nope"; the default was used.',
            'Unknown task category "Nope"; left empty.',
        ]);
});

// REQUIREMENT CHANGED (user decision 2026-10-22): the legacy always sends a creator, the "Utente legacy"
// stand-in when its chain finds none; qnet-2 no longer falls back to the actor.
it('AC-005 fails the row when the legacy sends no creator', function () {
    $run = runLegacyTasksImport([legacyTaskRecord(['creator_user_id' => null])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Task::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('No creator in the legacy; the task is not imported.');
});

it('AC-005 fails the row, leaving no task, when a person is not migrated', function (string $field, mixed $value, string $role) {
    User::factory()->create(['old_id' => 61]);

    $run = runLegacyTasksImport([legacyTaskRecord([
        'creator_user_id' => 61,
        'assignee_user_ids' => [61],
        $field => $value,
    ])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Task::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain("{$role} (legacy user id 999) not migrated");
})->with([
    'creator' => ['creator_user_id', 999, 'Creator'],
    'requester' => ['requester_user_id', 999, 'Requester'],
    'assignee' => ['assignee_user_ids', [999], 'Assignee'],
    'watcher' => ['watcher_user_ids', [999], 'Watcher'],
]);

it('AC-005 imports a task whose deleted legacy users arrive as the "Utente legacy" stand-in', function () {
    // The legacy sends 999999999 (LegacyPlaceholderUser) for every deleted user, in any role (decision 2026-10-21).
    $standIn = User::factory()->create(['old_id' => 999999999]);

    $run = runLegacyTasksImport([legacyTaskRecord([
        'creator_user_id' => 999999999,
        'requester_user_id' => 999999999,
        'assignee_user_ids' => [999999999],
    ])]);

    $task = Task::query()->where('old_id', 500)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($task->creator_id)->toBe($standIn->id)
        ->and($task->requester_id)->toBe($standIn->id)
        ->and($task->assignees()->pluck('users.id')->all())->toBe([$standIn->id]);
});

it('AC-006 syncs assignees and watchers into their pivots', function () {
    $first = User::factory()->create(['old_id' => 71]);
    $second = User::factory()->create(['old_id' => 72]);
    $watcher = User::factory()->create(['old_id' => 73]);
    $requester = User::factory()->create(['old_id' => 74]);

    runLegacyTasksImport([legacyTaskRecord([
        'requester_user_id' => 74,
        'assignee_user_ids' => [71, 72],
        'watcher_user_ids' => [73],
    ])]);

    $task = Task::query()->where('old_id', 500)->sole();

    expect($task->requester_id)->toBe($requester->id)
        ->and($task->assignees()->pluck('users.id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($task->watchers()->pluck('users.id')->all())->toBe([$watcher->id]);
});

it('AC-007 hangs a sub-task off its imported parent and imports a task with a missing parent as a root', function () {
    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 500]),
        legacyTaskRecord(['id' => 501, 'parent_id' => 500, 'subtask_position' => 3]),
        legacyTaskRecord(['id' => 502, 'parent_id' => 9999, 'subtask_position' => 1]),
    ]);

    $parent = Task::query()->where('old_id', 500)->sole();
    $child = Task::query()->where('old_id', 501)->sole();
    $orphan = Task::query()->where('old_id', 502)->sole();

    expect($child->parent_task_id)->toBe($parent->id)
        ->and($child->subtask_position)->toBe(3)
        ->and($orphan->parent_task_id)->toBeNull()
        ->and($run->failed_rows)->toBe(0)
        ->and(legacyTaskWarnings($run))->toBe(['Parent task (legacy id 9999) not migrated; imported as a root task.']);
});

it('AC-008 fails the row when the work order is not migrated', function () {
    $run = runLegacyTasksImport([legacyTaskRecord(['work_order_id' => 999])]);

    expect($run->failed_rows)->toBe(1)
        ->and(Task::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('Work order (legacy id 999) not migrated');
});

it('AC-008 keeps the legacy client when it is the work order client, else imposes the work order one with a warning', function () {
    [$workOrder, $registry] = migratedWorkOrder();
    $stranger = Registry::factory()->create(['old_id' => 701]);

    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 501, 'work_order_id' => 17, 'registry_id' => 700]),
        legacyTaskRecord(['id' => 502, 'work_order_id' => 17, 'registry_id' => 701]),
        legacyTaskRecord(['id' => 503, 'work_order_id' => 17]),
    ]);

    $registryOf = fn (int $oldId): ?int => Task::query()->where('old_id', $oldId)->value('registry_id');

    expect(Task::query()->where('old_id', 501)->value('work_order_id'))->toBe($workOrder->id)
        ->and($registryOf(501))->toBe($registry->id)
        ->and($registryOf(502))->toBe($registry->id)
        ->and($registryOf(503))->toBe($registry->id)
        ->and($stranger->exists)->toBeTrue()
        ->and(legacyTaskWarnings($run))->toBe(["Registry (legacy id 701) is not a client of the work order; the work order's client was used."]);
});

it('AC-009 puts a root task in the named stage with its position, and ignores the stage on a sub-task', function () {
    [$workOrder] = migratedWorkOrder();
    $stage = WorkOrderStage::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Collaudo']);

    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 500, 'work_order_id' => 17, 'work_order_stage_name' => ' collaudo ', 'stage_position' => 4]),
        legacyTaskRecord(['id' => 501, 'work_order_id' => 17, 'work_order_stage_name' => 'Missing']),
        legacyTaskRecord(['id' => 502, 'parent_id' => 500, 'work_order_id' => 17, 'work_order_stage_name' => 'Collaudo']),
    ]);

    $root = Task::query()->where('old_id', 500)->sole();

    expect($root->work_order_stage_id)->toBe($stage->id)
        ->and($root->stage_position)->toBe(4)
        ->and(Task::query()->where('old_id', 501)->value('work_order_stage_id'))->toBeNull()
        ->and(Task::query()->where('old_id', 502)->value('work_order_stage_id'))->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe(['Stage "Missing" not found on the work order; imported without stage.']);
});

it('AC-010 nulls the registry, referent and opportunity not migrated, and a referent of another registry', function () {
    $registry = Registry::factory()->create(['old_id' => 700]);
    $own = Referent::factory()->create(['old_id' => 800]);
    $own->registries()->attach($registry->id);
    Referent::factory()->create(['old_id' => 801]);
    $opportunity = Opportunity::factory()->create(['old_id' => 900]);

    $run = runLegacyTasksImport([
        legacyTaskRecord(['id' => 500, 'registry_id' => 700, 'referent_id' => 800, 'opportunity_id' => 900]),
        legacyTaskRecord(['id' => 501, 'registry_id' => 7000, 'referent_id' => 8000, 'opportunity_id' => 9000]),
        legacyTaskRecord(['id' => 502, 'registry_id' => 700, 'referent_id' => 801]),
    ]);

    $complete = Task::query()->where('old_id', 500)->sole();
    $missing = Task::query()->where('old_id', 501)->sole();
    $foreign = Task::query()->where('old_id', 502)->sole();

    expect($complete->registry_id)->toBe($registry->id)
        ->and($complete->referent_id)->toBe($own->id)
        ->and($complete->opportunity_id)->toBe($opportunity->id)
        ->and($missing->registry_id)->toBeNull()
        ->and($missing->referent_id)->toBeNull()
        ->and($missing->opportunity_id)->toBeNull()
        ->and($foreign->registry_id)->toBe($registry->id)
        ->and($foreign->referent_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe([
            'Registry (legacy id 7000) not migrated; left empty.',
            'Referent (legacy id 8000) not migrated; left empty.',
            'Opportunity (legacy id 9000) not migrated; left empty.',
            "Referent (legacy id 801) does not belong to the task's registry; left empty.",
        ]);
});

it('AC-011 truncates a title over 191 characters and copies the record warnings', function () {
    $run = runLegacyTasksImport([legacyTaskRecord([
        'title' => str_repeat('a', 200),
        'warnings' => ['Legacy: parent_id 7 removed.'],
    ])]);

    expect(mb_strlen(Task::query()->where('old_id', 500)->value('title')))->toBe(191)
        ->and(legacyTaskWarnings($run))->toBe([
            'Legacy: parent_id 7 removed.',
            'Title truncated to 191 characters.',
        ]);
});

it('AC-014 registers tasks after work-orders with the D-11 dependencies', function () {
    $source = app(MigrationRegistry::class)->resolve('tasks');
    $dependencies = collect($source->dependencies())->mapWithKeys(fn ($dependency) => [$dependency->source => $dependency->required])->all();

    expect($source->key())->toBe('tasks')
        ->and($source->endpoint())->toBe('tasks')
        ->and($dependencies)->toBe([
            'users' => true, 'work-orders' => true, 'registries' => false, 'referents' => false, 'opportunities' => false,
        ])
        ->and(MigrationOrder::phaseOf('tasks'))->toBeGreaterThan(MigrationOrder::phaseOf('work-orders'));
});
