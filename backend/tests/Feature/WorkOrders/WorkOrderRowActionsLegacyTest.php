<?php

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * Spec 0405: the Commesse row carries the legacy `OrdersTable` actions —
 * the eye, Responsabili e Partecipanti, Info and the Storico Note / Task /
 * Documenti menu — plus Elimina in the menu when the policy allows it
 * (user request 2026-11-19, REQUIREMENT CHANGED from spec 0405 D-5).
 */

/** @return array<int, string> the action keys of the only grid row */
function legacyRowActions(): array
{
    return test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items.0.actions');
}

function grantWorkOrderRowPermission(User $user, string $permission): User
{
    Permission::findOrCreate($permission);
    $user->givePermissionTo($permission);

    return $user;
}

it('AC-001: a reader gets view, team and the Info and Storico Note tabs only', function () {
    // Every right that used to add a row action, none of which may come back.
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'update', 'viewActivity']));
    WorkOrder::factory()->create();

    expect(legacyRowActions())->toBe(['view', 'team', 'info', 'notes']);
});

it('offers Elimina on the row to who holds work-orders.delete', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'delete']));
    WorkOrder::factory()->create();

    expect(legacyRowActions())->toBe(['view', 'team', 'info', 'notes', 'delete']);
});

it('AC-001: the Task and Documenti tabs follow their own detail-tab gates', function () {
    $actor = grantWorkOrderRowPermission(workOrderUserWith(['viewAny', 'view']), 'tasks.viewAny');
    grantWorkOrderRowPermission($actor, 'work-orders.viewDocuments');
    Sanctum::actingAs($actor);
    WorkOrder::factory()->create();

    expect(legacyRowActions())->toBe(['view', 'team', 'info', 'notes', 'tasks', 'documents']);
});

it('AC-001: the catalog keeps Info on the row and folds the other tabs and Elimina into the menu', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'delete']));

    $actions = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->keyBy('key');

    expect($actions->keys()->all())->toBe(['view', 'team', 'info', 'notes', 'delete'])
        ->and($actions['view']['icon'])->toBe('eye')
        ->and($actions->filter(fn (array $action): bool => ($action['menu'] ?? false) === true)->keys()->all())
        ->toBe(['notes', 'delete']);
});

// The proforma state came back as the "€" column (spec 0407): only the row action stays gone.
it('AC-001: the grid row no longer carries the proforma action', function () {
    Sanctum::actingAs(grantWorkOrderRowPermission(workOrderUserWith(['viewAny', 'view']), 'proforma-requests.create'));
    WorkOrder::factory()->create();

    expect(legacyRowActions())->not->toContain('proforma');
});
