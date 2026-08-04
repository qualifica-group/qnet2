<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// User decision 2026-08-04: "Richieste di modifica" hangs BENEATH "Gestione
// Richieste" (the same Leads -> Import shape), not as a flat sibling of the
// opportunities-group children — the only protected field today is that
// module's "Fonte", so the queue is a satellite of the worklist.
// ---------------------------------------------------------------------------

/**
 * The `field-change-requests` node as the real /api/navigation tree exposes
 * it, reached through its parent, or null when either level is filtered out.
 */
function navigationChangeRequestsItem(): ?array
{
    $groups = collect(test()->getJson('/api/navigation')->assertOk()->json('data'));
    $group = $groups->firstWhere('key', 'opportunities-group');

    if ($group === null) {
        return null;
    }

    $parent = collect($group['children'])->firstWhere('key', 'request-management');

    if ($parent === null) {
        return null;
    }

    return collect($parent['children'])->firstWhere('key', 'field-change-requests');
}

function actorWithPermissions(array $permissions): User
{
    $actor = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $actor->givePermissionTo($permission);
    }

    return $actor;
}

it('nests the field-change-requests item under request-management, never as its sibling', function () {
    Sanctum::actingAs(actorWithPermissions([
        'request-management.view',
        'field-change-requests.view',
    ]));

    $item = navigationChangeRequestsItem();

    expect($item)->not->toBeNull()
        ->and($item['route'])->toBe('/field-change-requests')
        ->and($item['label'])->toBe('navigation.fieldChangeRequests');

    $group = collect($this->getJson('/api/navigation')->json('data'))
        ->firstWhere('key', 'opportunities-group');

    expect(collect($group['children'])->firstWhere('key', 'field-change-requests'))->toBeNull();
});

it('drops the field-change-requests item without its own view permission', function () {
    Sanctum::actingAs(actorWithPermissions(['request-management.view']));

    expect(navigationChangeRequestsItem())->toBeNull();
});

it('drops the field-change-requests item with the parent it now hangs from', function () {
    // NavigationService::filter() skips a denied parent before recursing, so
    // nesting makes `request-management.view` a prerequisite of the entry.
    // Intended: the page is supervisor-only, and that role holds both.
    Sanctum::actingAs(actorWithPermissions(['field-change-requests.view']));

    expect(navigationChangeRequestsItem())->toBeNull();
});
