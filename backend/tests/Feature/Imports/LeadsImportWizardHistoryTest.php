<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function historyLeadsActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    // The MODULE gate (spec 0034): index() is now governed by
    // `import-runs.viewAny` instead of `{resource}.import`.
    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['viewAny']);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// AC-018 — GET /api/imports/{domain} (paginated history, every operator's runs)
// ---------------------------------------------------------------------------

it('AC-018: lists every operator\'s runs for the domain, paginated', function () {
    $actor = historyLeadsActorWith(['import']);
    $otherUser = User::factory()->create();
    Sanctum::actingAs($actor);

    ImportRun::factory()->count(2)->create(['user_id' => $actor->id, 'resource' => 'leads']);
    // Started by another user — listed too (runs are shared).
    ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);
    // The actor's own, but a different domain — must never appear.
    ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'business-functions']);

    // paginatedResponse() is a FLAT {items, pagination} shape (no success/
    // data envelope), mirroring GET /api/tables/{domain}/rows exactly.
    $response = $this->getJson('/api/imports/leads?page=1&per_page=2')
        ->assertOk()
        ->assertJsonPath('pagination.total', 3)
        ->assertJsonCount(2, 'items');

    foreach ($response->json('items') as $item) {
        expect($item['resource'])->toBe('leads');
    }

    $this->getJson('/api/imports/leads?page=2&per_page=2')
        ->assertOk()
        ->assertJsonCount(1, 'items');
});

it('the frozen show shape fields are still present on every history item', function () {
    $actor = historyLeadsActorWith(['import']);
    Sanctum::actingAs($actor);

    ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);

    $this->getJson('/api/imports/leads')
        ->assertOk()
        ->assertJsonPath('items.0.status', 'reviewing')
        ->assertJsonStructure(['items' => [['id', 'resource', 'status', 'total_rows', 'valid_rows', 'warning_rows', 'error_rows', 'duplicate_rows', 'has_error_report', 'created_at']]]);
});

it('403 without leads.import', function () {
    $actor = historyLeadsActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/leads')->assertForbidden();
});

it('404 for an unregistered domain', function () {
    $actor = historyLeadsActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/unknown-domain')->assertNotFound();
});
