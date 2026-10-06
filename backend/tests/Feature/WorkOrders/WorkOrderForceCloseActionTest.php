<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * "Chiusura forzata" as an action, not a field (user directive 2026-10-06):
 * the grid row and the detail offer `force_close` on an open commessa and
 * its inverse `reopen` on a force-closed one, both gated on the record-level
 * update rule of the PATCH they send.
 */

/** The action keys the grid offers on the row titled $title. */
function workOrderRowActions(string $title): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items');

    return collect($items)->firstWhere('title', $title)['actions'];
}

it('offers force_close on an open row and reopen on a force-closed one', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'update']));
    WorkOrder::factory()->create(['title' => 'Aperta', 'is_force_closed' => false]);
    WorkOrder::factory()->create(['title' => 'Chiusa', 'is_force_closed' => true, 'force_close_reason' => 'Annullata']);

    expect(workOrderRowActions('Aperta'))->toContain('force_close')->not->toContain('reopen')
        ->and(workOrderRowActions('Chiusa'))->toContain('reopen')->not->toContain('force_close');
});

it('offers neither on the grid without work-orders.update', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    WorkOrder::factory()->create(['title' => 'Aperta', 'is_force_closed' => false]);

    expect(workOrderRowActions('Aperta'))->not->toContain('force_close')->not->toContain('reopen');
});

it('declares both actions in the grid catalogue for an actor who may update', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $keys = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->pluck('key');

    expect($keys)->toContain('force_close')->toContain('reopen');
});

it('reports the matching detail action by the closure state', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'update']));
    $open = WorkOrder::factory()->create(['is_force_closed' => false]);
    $closed = WorkOrder::factory()->create(['is_force_closed' => true, 'force_close_reason' => 'Annullata']);

    $this->getJson("/api/work-orders/{$open->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.force_close', true)
        ->assertJsonPath('permissions.actions.reopen', false);

    $this->getJson("/api/work-orders/{$closed->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.force_close', false)
        ->assertJsonPath('permissions.actions.reopen', true);
});

it('reports neither detail action without work-orders.update', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    $open = WorkOrder::factory()->create(['is_force_closed' => false]);

    $this->getJson("/api/work-orders/{$open->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.force_close', false)
        ->assertJsonPath('permissions.actions.reopen', false);
});
