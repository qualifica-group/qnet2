<?php

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
| Segnatempo note mirrored into the commessa's internal_notes
| (user decision 2026-09-14: plain text, kept in sync on update/delete)
|--------------------------------------------------------------------------
*/

if (! function_exists('workOrderNoteActor')) {
    function workOrderNoteActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['time-entries.create', 'time-entries.view', 'time-entries.update', 'time-entries.delete']);

        return $user;
    }
}

if (! function_exists('workOrderNotePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function workOrderNotePayload(array $overrides = []): array
    {
        return [
            'date' => '2026-09-14',
            'title' => 'Sopralluogo',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
            ...$overrides,
        ];
    }
}

it('appends the note to the commessa internal_notes on create', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Nota manuale']);

    $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => '  Cliente assente  ',
    ]))->assertCreated();

    expect($workOrder->fresh()->internal_notes)->toBe("Nota manuale\n\nCliente assente");
});

it('fills empty internal_notes with the note alone', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => null]);

    $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Primo intervento',
    ]))->assertCreated();

    expect($workOrder->fresh()->internal_notes)->toBe('Primo intervento');
});

it('leaves the commessa untouched when the segnatempo has no note', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Nota manuale']);

    $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => '   ',
    ]))->assertCreated();

    expect($workOrder->fresh()->internal_notes)->toBe('Nota manuale');
});

it('mirrors the note onto the commessa of the linked Task', function () {
    $actor = workOrderNoteActor();
    Permission::findOrCreate('tasks.view');
    $actor->givePermissionTo('tasks.view');
    $workOrder = WorkOrder::factory()->create(['internal_notes' => null]);
    $task = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/time-entries", [
        'date' => '2026-09-14',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 30,
        'notes' => 'Dal task',
    ])->assertCreated();

    expect($workOrder->fresh()->internal_notes)->toBe('Dal task');
});

it('replaces the copied block in place when the note changes', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Prima']);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Vecchia',
    ]))->assertCreated()->json('data.id');

    $workOrder->update(['internal_notes' => "Prima\n\nVecchia\n\nDopo"]);

    $this->putJson("/api/time-entries/{$id}", workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Nuova',
    ]))->assertOk();

    expect($workOrder->fresh()->internal_notes)->toBe("Prima\n\nNuova\n\nDopo")
        ->and(TimeEntry::query()->find($id)->work_order_note)->toBe('Nuova');
});

it('removes the copied block when the note is cleared', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Prima']);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Da togliere',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => null,
    ]))->assertOk();

    expect($workOrder->fresh()->internal_notes)->toBe('Prima');
});

it('moves the copied block when the commessa changes', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $from = WorkOrder::factory()->create(['internal_notes' => null]);
    $to = WorkOrder::factory()->create(['internal_notes' => 'Esistente']);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $from->id,
        'notes' => 'Spostata',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", workOrderNotePayload([
        'work_order_id' => $to->id,
        'notes' => 'Spostata',
    ]))->assertOk();

    expect($from->fresh()->internal_notes)->toBeNull()
        ->and($to->fresh()->internal_notes)->toBe("Esistente\n\nSpostata");
});

it('removes the copied block when the commessa is unlinked', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => 'Manuale']);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Scollegata',
    ]))->assertCreated()->json('data.id');

    $this->putJson("/api/time-entries/{$id}", workOrderNotePayload(['notes' => 'Scollegata']))->assertOk();

    expect($workOrder->fresh()->internal_notes)->toBe('Manuale')
        ->and(TimeEntry::query()->find($id)->work_order_note)->toBeNull();
});

it('removes the copied block when the segnatempo is deleted', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => null]);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Prima riga',
    ]))->assertCreated()->json('data.id');

    $workOrder->update(['internal_notes' => "Prima riga\n\nManuale"]);

    $this->deleteJson("/api/time-entries/{$id}")->assertOk();

    expect($workOrder->fresh()->internal_notes)->toBe('Manuale');
});

it('never overwrites a copied block the user edited by hand', function () {
    Sanctum::actingAs(workOrderNoteActor());
    $workOrder = WorkOrder::factory()->create(['internal_notes' => null]);

    $id = $this->postJson('/api/time-entries', workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Originale',
    ]))->assertCreated()->json('data.id');

    $workOrder->update(['internal_notes' => 'Originale corretta a mano']);

    $this->putJson("/api/time-entries/{$id}", workOrderNotePayload([
        'work_order_id' => $workOrder->id,
        'notes' => 'Aggiornata',
    ]))->assertOk();

    expect($workOrder->fresh()->internal_notes)->toBe("Originale corretta a mano\n\nAggiornata");
});
