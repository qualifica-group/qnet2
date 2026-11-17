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
 * Documenti menu — and nothing else.
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
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']));
    WorkOrder::factory()->create();

    expect(legacyRowActions())->toBe(['view', 'team', 'info', 'notes']);
});

it('AC-001: the Task and Documenti tabs follow their own detail-tab gates', function () {
    $actor = grantWorkOrderRowPermission(workOrderUserWith(['viewAny', 'view']), 'tasks.viewAny');
    grantWorkOrderRowPermission($actor, 'work-orders.viewDocuments');
    Sanctum::actingAs($actor);
    WorkOrder::factory()->create();

    expect(legacyRowActions())->toBe(['view', 'team', 'info', 'notes', 'tasks', 'documents']);
});

it('AC-001: the catalog keeps Info on the row, folds the other tabs into the menu and keeps delete for the bulk delete only', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'delete']));

    $actions = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.actions'))->keyBy('key');

    expect($actions->keys()->all())->toBe(['view', 'team', 'info', 'notes', 'delete'])
        ->and($actions['view']['icon'])->toBe('eye')
        ->and($actions->filter(fn (array $action): bool => ($action['menu'] ?? false) === true)->keys()->all())
        ->toBe(['notes']);
});

// The proforma state came back as the "€" column (spec 0407): only the row action stays gone.
it('AC-001: the grid row no longer carries the proforma action', function () {
    Sanctum::actingAs(grantWorkOrderRowPermission(workOrderUserWith(['viewAny', 'view']), 'proforma-requests.create'));
    WorkOrder::factory()->create();

    expect(legacyRowActions())->not->toContain('proforma');
});
