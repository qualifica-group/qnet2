<?php

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * "Chiusura forzata" as an action, not a field (user directive 2026-10-06):
 * the detail offers `force_close` on an open commessa and its inverse
 * `reopen` on a force-closed one, both gated on the record-level update rule
 * of the PATCH they send. The grid row no longer offers them (spec 0405).
 */

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
