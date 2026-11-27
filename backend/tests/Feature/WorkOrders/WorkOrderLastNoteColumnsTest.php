<?php

use App\Models\Note;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * User request 2026-11-27: the Commesse grid gets the legacy `OrdersTable`
 * "Ultima Nota" (the latest note of the commessa) and "U.N. Responsabile"
 * (the latest note written by a Responsabile): the date in the cell, author
 * and text on hover.
 */

function lastNoteOn(WorkOrder $workOrder, User $author, string $createdAt, string $body = '<p>Testo</p>'): Note
{
    return Note::factory()->for($workOrder, 'notable')->for($author, 'author')->create([
        'body' => $body,
        'created_at' => $createdAt,
    ]);
}

/**
 * `team`: a supervisor's note on the 10th, a newer non-supervisor reply on the
 * 20th and a still newer, soft-deleted supervisor note; `outsider`: only a
 * non-supervisor note on the 5th; `empty`: no notes.
 *
 * @return array{team: WorkOrder, outsider: WorkOrder, empty: WorkOrder, supervisor: User, other: User}
 */
function lastNoteFixture(): array
{
    $supervisor = User::factory()->create(['name' => 'Mario Rossi']);
    $other = User::factory()->create(['name' => 'Anna Bianchi']);

    $team = WorkOrder::factory()->create();
    $team->supervisors()->attach($supervisor);
    $root = lastNoteOn($team, $supervisor, '2026-11-10 09:00:00', '<p>Chiamato il <strong>cliente</strong></p>');
    $reply = lastNoteOn($team, $other, '2026-11-20 15:30:00', '<p>Risposta</p>');
    $reply->parent()->associate($root)->save();
    lastNoteOn($team, $supervisor, '2026-11-25 10:00:00')->delete();

    $outsider = WorkOrder::factory()->create();
    $outsider->supervisors()->attach($supervisor);
    lastNoteOn($outsider, $other, '2026-11-05 08:00:00');

    return ['team' => $team, 'outsider' => $outsider, 'empty' => WorkOrder::factory()->create(), 'supervisor' => $supervisor, 'other' => $other];
}

/**
 * @param  array<string, mixed>  $body
 * @return array<int, array<string, mixed>>
 */
function lastNoteRows(array $body = []): array
{
    return test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, ...$body])
        ->assertOk()
        ->json('items');
}

it('rows carry the latest note and the latest supervisor note, deleted notes excluded', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $orders = lastNoteFixture();

    $rows = collect(lastNoteRows())->keyBy('id');
    $team = $rows[$orders['team']->id];

    expect(substr((string) $team['last_note_at'], 0, 10))->toBe('2026-11-20')
        ->and($team['last_note_at_author'])->toBe('Anna Bianchi')
        ->and($team['last_note_at_excerpt'])->toBe('Risposta')
        ->and(substr((string) $team['supervisor_last_note_at'], 0, 10))->toBe('2026-11-10')
        ->and($team['supervisor_last_note_at_author'])->toBe('Mario Rossi')
        ->and($team['supervisor_last_note_at_excerpt'])->toBe('Chiamato il cliente')
        ->and(substr((string) $rows[$orders['outsider']->id]['last_note_at'], 0, 10))->toBe('2026-11-05')
        ->and($rows[$orders['outsider']->id]['supervisor_last_note_at'])->toBeNull()
        ->and($rows[$orders['empty']->id]['last_note_at'])->toBeNull()
        ->and($rows[$orders['empty']->id]['last_note_at_excerpt'])->toBeNull();
});

it('declares both columns as sortable, date-filterable columns', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $columns = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))->keyBy('id');

    foreach (['last_note_at', 'supervisor_last_note_at'] as $id) {
        expect($columns[$id]['label'])->toBe("workOrders.columns.{$id}")
            ->and($columns[$id]['type'])->toBe('date')
            ->and($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterType'])->toBe('date');
    }
});

it('filters by the note date with the same rule as the cell', function (string $columnId, array $filter, array $expected) {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $orders = lastNoteFixture();

    $ids = collect(lastNoteRows(['filterModel' => [$columnId => ['filterType' => 'date', ...$filter]]]))->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing(array_map(fn (string $key): int => $orders[$key]->id, $expected));
})->with([
    'last note on the 20th' => ['last_note_at', ['type' => 'equals', 'dateFrom' => '2026-11-20'], ['team']],
    'last note after the 1st' => ['last_note_at', ['type' => 'greaterThan', 'dateFrom' => '2026-11-01'], ['team', 'outsider']],
    'supervisor note in range' => ['supervisor_last_note_at', ['type' => 'inRange', 'dateFrom' => '2026-11-01', 'dateTo' => '2026-11-15'], ['team']],
    'deleted note never matches' => ['supervisor_last_note_at', ['type' => 'equals', 'dateFrom' => '2026-11-25'], []],
]);

it('sorts by the latest note date', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $orders = lastNoteFixture();

    $ids = collect(lastNoteRows(['sortModel' => [['colId' => 'last_note_at', 'sort' => 'desc']]]))->pluck('id')->all();

    expect(array_slice($ids, 0, 2))->toBe([$orders['team']->id, $orders['outsider']->id]);
});
