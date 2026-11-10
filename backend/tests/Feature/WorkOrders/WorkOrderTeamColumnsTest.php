<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// User request 2026-11-09: the "Partecipanti" column beside the Responsabili,
// and both as person pickers in the advanced filters, as on the Task grid.

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, int>
 */
function workOrderIdsMatching(array $payload): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, ...$payload])
        ->assertOk()
        ->json('items');

    return collect($items)->pluck('id')->sort()->values()->all();
}

function workOrderWithParticipant(User $participant): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach($participant->id, ['position' => 1]);

    return $workOrder;
}

it('declares the participants column right after the supervisors, editable as a list of people', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $columns = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'));
    $supervisorsIndex = $columns->search(static fn (array $column): bool => $column['id'] === 'supervisors');

    expect($columns[$supervisorsIndex + 1])->toMatchArray([
        'id' => 'participants',
        'label' => 'workOrders.columns.participants',
        'visible' => true,
        'sortable' => false,
        'filterType' => 'set',
        'editable' => true,
        'editor' => 'multiselect',
    ]);
});

it('returns the participants in the row in their position order, with the avatar field', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $first = User::factory()->create(['name' => 'Zeno Primo']);
    $second = User::factory()->create(['name' => 'Anna Seconda']);
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach([$second->id => ['position' => 2], $first->id => ['position' => 1]]);

    $row = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items.0');

    expect($row['participants'])->toBe([
        ['id' => $first->id, 'name' => 'Zeno Primo', 'avatar_url' => null],
        ['id' => $second->id, 'name' => 'Anna Seconda', 'avatar_url' => null],
    ]);
});

it('offers supervisors and participants as multi-value person pickers in the advanced filters', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $filters = array_values(array_filter(
        $this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.advancedFilters'),
        static fn (array $filter): bool => in_array($filter['name'], ['supervisors', 'participants'], true),
    ));

    expect(array_column($filters, 'name'))->toBe(['supervisors', 'participants'])
        ->and(array_column($filters, 'label'))->toBe(['workOrders.advancedFilters.supervisors', 'workOrders.advancedFilters.participants']);

    foreach ($filters as $filter) {
        expect($filter['type'])->toBe('relation')
            ->and($filter['multiple'])->toBeTrue()
            ->and($filter['source'])->toBe(['resource' => 'users']);
    }
});

it('filters by participant, matching a work order when any picked person takes part', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $picked = User::factory()->create();
    $alsoPicked = User::factory()->create();
    $first = workOrderWithParticipant($picked);
    $second = workOrderWithParticipant($alsoPicked);
    workOrderWithParticipant(User::factory()->create());
    WorkOrder::factory()->create();

    expect(workOrderIdsMatching(['advancedFilters' => ['participants' => [$picked->id, $alsoPicked->id]]]))
        ->toBe([$first->id, $second->id]);
});

it('filters by supervisor without matching the participants, and combines the two in AND', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $person = User::factory()->create();
    $otherPerson = User::factory()->create();
    $supervised = WorkOrder::factory()->create();
    $supervised->supervisors()->attach($person->id);
    workOrderWithParticipant($person);
    $both = workOrderWithParticipant($otherPerson);
    $both->supervisors()->attach($person->id);

    expect(workOrderIdsMatching(['advancedFilters' => ['supervisors' => [$person->id]]]))
        ->toBe([$supervised->id, $both->id])
        ->and(workOrderIdsMatching(['advancedFilters' => ['supervisors' => [$person->id], 'participants' => [$otherPerson->id]]]))
        ->toBe([$both->id]);
});

it('keeps the advanced filters inside the visibility scope', function () {
    $actor = workOrderUserWith(['viewAny']);
    $actor->revokePermissionTo('work-orders.viewAll');
    Sanctum::actingAs($actor);
    $person = User::factory()->create();
    workOrderWithParticipant($person);
    $mine = workOrderWithParticipant($person);
    $mine->supervisors()->attach($actor->id);

    expect(workOrderIdsMatching(['advancedFilters' => ['participants' => [$person->id]]]))->toBe([$mine->id]);
});

it('filters the participants column by name and by the blank entry', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $matching = workOrderWithParticipant(User::factory()->create(['name' => 'Laura Bianchi']));
    workOrderWithParticipant(User::factory()->create(['name' => 'Marco Rossi']));
    $empty = WorkOrder::factory()->create();

    expect(workOrderIdsMatching(['filterModel' => ['participants' => ['filterType' => 'set', 'values' => ['Laura Bianchi']]]]))
        ->toBe([$matching->id])
        ->and(workOrderIdsMatching(['filterModel' => ['participants' => ['filterType' => 'set', 'values' => [null]]]]))
        ->toBe([$empty->id]);
});

it('lists the distinct participant names, plus the blank entry when a work order has none', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    workOrderWithParticipant(User::factory()->create(['name' => 'Marco Rossi']));
    workOrderWithParticipant(User::factory()->create(['name' => 'Laura Bianchi']));
    WorkOrder::factory()->create();

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'participants', 'limit' => 25])
        ->assertOk()
        ->json('data.values');

    expect(array_values(array_filter($values)))->toBe(['Laura Bianchi', 'Marco Rossi'])
        ->and($values)->toContain(null);
});

it('edits the participants from the cell: whoever stays keeps their position, a newcomer takes the first free one', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $staying = User::factory()->create();
    $leaving = User::factory()->create();
    $newcomer = User::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach([$leaving->id => ['position' => 1], $staying->id => ['position' => 2]]);

    $this->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", ['column' => 'participants', 'value' => [$staying->id, $newcomer->id]])
        ->assertOk();

    expect($workOrder->fresh()->participants->mapWithKeys(static fn (User $user): array => [$user->id => $user->pivot->position])->all())
        ->toBe([$newcomer->id => 1, $staying->id => 2]);
});

it('clears the participants from the cell', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $workOrder = workOrderWithParticipant(User::factory()->create());

    $this->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", ['column' => 'participants', 'value' => []])->assertOk();

    expect($workOrder->fresh()->participants)->toBeEmpty();
});

it('refuses the participants cell edit without the update permission', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $workOrder = WorkOrder::factory()->create();

    $this->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", ['column' => 'participants', 'value' => [User::factory()->create()->id]])
        ->assertForbidden();

    expect($workOrder->fresh()->participants)->toBeEmpty();
});

it('refuses an unknown advanced filter value shape on participants', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['participants' => 'not-a-list']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('advancedFilters.participants');
});
