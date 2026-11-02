<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// Spec 0341: the legacy segnatempo intervals, one time entry each.

beforeEach(function () {
    seedMigrationsConfig();
    $this->owner = User::factory()->create(['old_id' => 449]);
    $this->type = TaskType::factory()->create(['name' => 'Chiamata']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyTimeEntryRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 90001,
        'user_id' => 449,
        'date' => '2026-05-20',
        'title' => 'Chiamata cliente',
        'type_name' => 'Chiamata',
        'start_time' => '09:00',
        'end_time' => '09:45',
        'minutes' => 45,
        'notes' => "Nota giornata\nNota intervallo",
        'registry_id' => null,
        'opportunity_id' => null,
        'work_order_id' => null,
        'task_id' => null,
        'stage_name' => null,
        'created_at' => '2026-05-20 10:00:00',
        'updated_at' => '2026-05-21 08:00:00',
        'warnings' => [],
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyTimeEntriesImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/time-entries*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'time-entries']);
    runMigrationJobFor($run);

    return $run->fresh();
}

function legacyWorkOrderWithClient(int $oldId): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->forceFill(['old_id' => $oldId])->save();

    return $workOrder;
}

it('AC-001 imports an interval with owner, type, times, minutes, notes and legacy dates, quietly', function () {
    Notification::fake();
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord()], $actor);
    $entry = TimeEntry::query()->where('old_id', 90001)->sole();

    expect($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($entry->user_id)->toBe($this->owner->id)
        ->and($entry->date->toDateString())->toBe('2026-05-20')
        ->and($entry->title)->toBe('Chiamata cliente')
        ->and($entry->task_type_id)->toBe($this->type->id)
        ->and(substr((string) $entry->start_time, 0, 5))->toBe('09:00')
        ->and(substr((string) $entry->end_time, 0, 5))->toBe('09:45')
        ->and($entry->minutes)->toBe(45)
        ->and($entry->notes)->toBe("Nota giornata\nNota intervallo")
        ->and($entry->work_order_note_id)->toBeNull()
        ->and($entry->created_at->toDateTimeString())->toBe('2026-05-20 10:00:00')
        ->and($entry->updated_at->toDateTimeString())->toBe('2026-05-21 08:00:00')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);

    Notification::assertNothingSent();
});

it('AC-002 takes title, links and stage from a migrated task', function () {
    $workOrder = legacyWorkOrderWithClient(19123);
    $stage = WorkOrderStage::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Audit']);
    $task = Task::factory()->create([
        'title' => 'Audit fornitore',
        'work_order_id' => $workOrder->id,
        'work_order_stage_id' => $stage->id,
        'registry_id' => $workOrder->quote->opportunity->registry_id,
    ]);
    $task->forceFill(['old_id' => 7001])->save();
    $otherOpportunity = Opportunity::factory()->create();
    $otherOpportunity->forceFill(['old_id' => 3001])->save();

    runLegacyTimeEntriesImport([legacyTimeEntryRecord(['task_id' => 7001, 'title' => 'Legacy', 'opportunity_id' => 3001])]);
    $entry = TimeEntry::query()->sole();

    expect($entry->task_id)->toBe($task->id)
        ->and($entry->title)->toBe('Audit fornitore')
        ->and($entry->work_order_id)->toBe($workOrder->id)
        ->and($entry->opportunity_id)->toBeNull()
        ->and($entry->registry_id)->toBe($task->registry_id)
        ->and($entry->work_order_stage_id)->toBe($stage->id);
});

it('AC-003 fails the row on a user not migrated, an unknown type or minutes out of range', function (array $overrides, string $reason) {
    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord($overrides)]);

    expect($run->failed_rows)->toBe(1)
        ->and(TimeEntry::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain($reason);
})->with([
    'user not migrated' => [['user_id' => 99], 'User (legacy id 99) not migrated'],
    'unknown type' => [['type_name' => 'Sopralluogo'], 'Task type "Sopralluogo" not found'],
    'zero minutes' => [['minutes' => 0], 'Minutes must be between 1 and 1440'],
    'over a day' => [['minutes' => 1441], 'Minutes must be between 1 and 1440'],
]);

it('AC-004 imports without the links that are not migrated, with a warning each', function (array $overrides, array $warnings) {
    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord($overrides)]);
    $entry = TimeEntry::query()->sole();

    expect($run->created_rows)->toBe(1)
        ->and($entry->only(['registry_id', 'opportunity_id', 'work_order_id', 'task_id']))->toBe([
            'registry_id' => null, 'opportunity_id' => null, 'work_order_id' => null, 'task_id' => null,
        ])
        ->and(legacyTaskWarnings($run))->toBe($warnings);
})->with([
    'task and registry' => [['task_id' => 1, 'registry_id' => 2], [
        'Task (legacy id 1) not migrated; imported without it.',
        'Registry (legacy id 2) not migrated; imported without it.',
    ]],
    'work order and opportunity' => [['work_order_id' => 5, 'opportunity_id' => 6], [
        'Work order (legacy id 5) not migrated; imported without it.',
        'Opportunity (legacy id 6) not migrated; imported without it.',
    ]],
]);

it('AC-005 takes the client of the work order, drops a second record and matches the stage by name', function () {
    $workOrder = legacyWorkOrderWithClient(19123);
    $clientId = $workOrder->quote->opportunity->registry_id;
    $stage = WorkOrderStage::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Formazione']);
    $opportunity = Opportunity::factory()->create();
    $opportunity->forceFill(['old_id' => 3001])->save();
    $otherRegistry = Registry::factory()->create();
    $otherRegistry->forceFill(['old_id' => 8001])->save();

    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord([
        'work_order_id' => 19123, 'opportunity_id' => 3001, 'registry_id' => 8001, 'stage_name' => ' formazione ',
    ])]);
    $entry = TimeEntry::query()->sole();

    expect($entry->work_order_id)->toBe($workOrder->id)
        ->and($entry->opportunity_id)->toBeNull()
        ->and($entry->registry_id)->toBe($clientId)
        ->and($entry->work_order_stage_id)->toBe($stage->id)
        ->and(legacyTaskWarnings($run))->toBe([
            'Both a work order and an opportunity were sent; the opportunity was dropped.',
            'Registry (legacy id 8001) is not the client of the linked record; the record\'s client was used.',
        ]);
});

it('AC-005 imports without stage when the name is not among the work order stages', function () {
    legacyWorkOrderWithClient(19123);

    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord(['work_order_id' => 19123, 'stage_name' => 'Chiusura'])]);

    expect(TimeEntry::query()->sole()->work_order_stage_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toBe(['Stage "Chiusura" not found on the work order; imported without stage.']);
});

it('AC-006 fails an orphan sent by the legacy with its reason and imports the rest of the page', function () {
    $run = runLegacyTimeEntriesImport([
        ['id' => 'working_activity_intervals:5', 'orphan_reason' => 'Segnatempo dell\'utente 449 del 2026-05-20 «X»: Durata di 0 minuti.', 'warnings' => []],
        legacyTimeEntryRecord(),
    ]);

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(1)
        ->and(legacyTaskErrors($run)[0])->toContain('Durata di 0 minuti');
});

it('AC-007 skips an interval already imported without rewriting it', function () {
    $entry = TimeEntry::factory()->create(['title' => 'Already imported', 'task_type_id' => $this->type->id]);
    $entry->forceFill(['old_id' => 90001])->save();

    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord(['title' => 'Changed'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and(TimeEntry::query()->sole()->title)->toBe('Already imported');
});

it('AC-008 warns when the user already logged time in QNet that day and truncates long texts', function () {
    TimeEntry::factory()->create(['user_id' => $this->owner->id, 'date' => '2026-05-20', 'task_type_id' => $this->type->id]);

    $run = runLegacyTimeEntriesImport([legacyTimeEntryRecord([
        'title' => str_repeat('t', 200),
        'notes' => str_repeat('n', 5001),
    ])]);
    $entry = TimeEntry::query()->where('old_id', 90001)->sole();

    expect(mb_strlen($entry->title))->toBe(191)
        ->and(mb_strlen((string) $entry->notes))->toBe(5000)
        ->and(legacyTaskWarnings($run))->toBe([
            'The user already has time entries logged in QNet on 2026-05-20; imported alongside them.',
            'Title truncated to 191 characters.',
            'Notes truncated to 5000 characters.',
        ]);
});

it('AC-010 registers both sources in the last phase with their dependencies', function () {
    $registry = app(MigrationRegistry::class);
    $dependencies = fn (string $key): array => array_map(fn ($dependency) => $dependency->toArray(), $registry->resolve($key)->dependencies());

    expect(MigrationOrder::phases()[count(MigrationOrder::phases()) - 1])->toBe(['time-entries', 'time-entry-day-notes'])
        ->and($dependencies('time-entries'))->toBe([
            ['source' => 'users', 'required' => true],
            ['source' => 'registries', 'required' => false],
            ['source' => 'opportunities', 'required' => false],
            ['source' => 'work-orders', 'required' => false],
            ['source' => 'tasks', 'required' => false],
        ])
        ->and($dependencies('time-entry-day-notes'))->toBe([
            ['source' => 'users', 'required' => true],
        ]);
});
