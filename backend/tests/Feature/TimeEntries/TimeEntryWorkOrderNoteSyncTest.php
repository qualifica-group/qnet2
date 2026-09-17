<?php

use App\Models\Note;
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
| Segnatempo note mirrored as a comment on the commessa
| (user decision 2026-09-17: collaborative note, not internal_notes; kept in
| sync on update/delete; authored by the segnatempo owner)
|--------------------------------------------------------------------------
*/

if (! function_exists('timeEntryCommentActor')) {
    /**
     * @param  array<int, string>  $extraAbilities
     */
    function timeEntryCommentActor(array $extraAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['time-entries.create', 'time-entries.view', 'time-entries.update', 'time-entries.delete', ...$extraAbilities]);

        return $user;
    }
}

if (! function_exists('timeEntryCommentPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function timeEntryCommentPayload(array $overrides = []): array
    {
        return [
            'date' => '2026-09-17',
            'title' => 'Sopralluogo',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
            ...$overrides,
        ];
    }
}

if (! function_exists('workOrderComments')) {
    /**
     * @return array<int, string>
     */
    function workOrderComments(WorkOrder $workOrder): array
    {
        return Note::query()
            ->where('notable_type', $workOrder->getMorphClass())
            ->where('notable_id', $workOrder->id)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }
}

it('adds the note as a comment on the commessa and leaves internal_notes alone', function () {
    $actor = timeEntryCommentActor();
    Sanctum::actingAs($actor);
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Nota manuale']);

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => "  Cliente assente\n<b>richiamare</b>  ",
    ]))->assertCreated()->json('data.id');

    $note = Note::query()->sole();

    expect($workOrder->fresh()->internal_notes)->toBe('Nota manuale')
        ->and($note->notable_type)->toBe($workOrder->getMorphClass())
        ->and($note->notable_id)->toBe($workOrder->id)
        ->and($note->body)->toBe('<p>Cliente assente<br>&lt;b&gt;richiamare&lt;/b&gt;</p>')
        ->and($note->user_id)->toBe($actor->id)
        ->and($note->parent_id)->toBeNull()
        ->and($note->quote_id)->toBeNull()
        ->and(TimeEntry::query()->find($id)->work_order_note_id)->toBe($note->id);
});

it('shows the comment in the commessa notes thread', function () {
    foreach (['view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    Sanctum::actingAs(timeEntryCommentActor(['work-orders.view', 'work-orders.viewAll']));
    $workOrder = WorkOrder::factory()->create();

    $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Visibile nel thread',
    ]))->assertCreated();

    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.0.body', '<p>Visibile nel thread</p>');
});

it('adds no comment when the segnatempo has no note or no commessa', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => '   ',
    ]))->assertCreated();

    $this->postJson('/api/time-entries', timeEntryCommentPayload(['notes' => 'Senza commessa']))->assertCreated();

    expect(Note::query()->count())->toBe(0);
});

it('signs the comment with the segnatempo owner, not the acting admin', function () {
    $admin = timeEntryCommentActor(['time-entries.manageAll']);
    $owner = User::factory()->create();
    Sanctum::actingAs($admin);
    $workOrder = WorkOrder::factory()->create();

    $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'user_id' => $owner->id,
        'work_order_id' => $workOrder->id,
        'notes' => 'Per conto di',
    ]))->assertCreated();

    expect(Note::query()->sole()->user_id)->toBe($owner->id);
});

it('mirrors the note onto the commessa of the linked Task', function () {
    $actor = timeEntryCommentActor();
    Permission::findOrCreate('tasks.view');
    $actor->givePermissionTo('tasks.view');
    $workOrder = WorkOrder::factory()->create();
    $task = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/time-entries", [
        'date' => '2026-09-17',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 30,
        'notes' => 'Dal task',
    ])->assertCreated();

    expect(workOrderComments($workOrder))->toBe(['<p>Dal task</p>']);
});

it('rewrites the same comment when the note changes', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Vecchia',
    ]))->assertCreated()->json('data.id');
    $noteId = TimeEntry::query()->find($id)->work_order_note_id;

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Nuova',
    ]))->assertOk();

    expect(workOrderComments($workOrder))->toBe(['<p>Nuova</p>'])
        ->and(TimeEntry::query()->find($id)->work_order_note_id)->toBe($noteId);
});

it('leaves a comment edited in the thread alone when the note does not change', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Originale',
    ]))->assertCreated()->json('data.id');
    Note::query()->sole()->update(['body' => '<p>Corretta nel thread</p>']);

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Originale',
        'minutes' => 90,
    ]))->assertOk();

    expect(workOrderComments($workOrder))->toBe(['<p>Corretta nel thread</p>']);
});

it('deletes the comment when the note is cleared', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Da togliere',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => null,
    ]))->assertOk();

    expect(workOrderComments($workOrder))->toBe([])
        ->and(TimeEntry::query()->find($id)->work_order_note_id)->toBeNull();
});

it('moves the comment when the commessa changes', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $from = WorkOrder::factory()->create();
    $to = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $from->id,
        'notes' => 'Spostata',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload([
        'work_order_id' => $to->id,
        'notes' => 'Spostata',
    ]))->assertOk();

    expect(workOrderComments($from))->toBe([])
        ->and(workOrderComments($to))->toBe(['<p>Spostata</p>']);
});

it('deletes the comment when the commessa is unlinked', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Scollegata',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload(['notes' => 'Scollegata']))->assertOk();

    expect(workOrderComments($workOrder))->toBe([])
        ->and(TimeEntry::query()->find($id)->work_order_note_id)->toBeNull();
});

it('deletes the comment when the segnatempo is deleted', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Prima riga',
    ]))->assertCreated()->json('data.id');

    $this->deleteJson("/api/time-entries/{$id}")->assertOk();

    expect(workOrderComments($workOrder))->toBe([])
        ->and(Note::withTrashed()->count())->toBe(1);
});

it('adds a new comment when the previous one was deleted from the thread and the note changes', function () {
    Sanctum::actingAs(timeEntryCommentActor());
    $workOrder = WorkOrder::factory()->create();

    $id = $this->postJson('/api/time-entries', timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Prima',
    ]))->assertCreated()->json('data.id');
    Note::query()->sole()->delete();

    $this->putJson("/api/time-entries/{$id}", timeEntryCommentPayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Seconda',
    ]))->assertOk();

    expect(workOrderComments($workOrder))->toBe(['<p>Seconda</p>']);
});
