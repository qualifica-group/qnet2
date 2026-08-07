<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// D-6 hybrid authorization: read is inherited from the host entity, write
// requires `notes.create` ANDed with that same read access. Author-only
// mutability (D-8), with Gate::before still granting the super-admin. Spec
// 0086, D-9: read access is re-keyed on the Opportunity's own Offerte
// (`RequestManagementNotable::authorizeRead()`) — an actor reads when they
// SUPERVISE at least one Offerta of the Opportunity (D-3, `quotes.
// supervisor_id`), not when they hold any opportunity-manager pivot slot.

uses(RefreshDatabase::class);

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    function noteManagedOpportunity(User $supervisor): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — read inherited from the host entity
// ---------------------------------------------------------------------------

it('a GA with request-management.view reads the notes of a record they manage (AC-030)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('meta.has_more', false);
});

it('a GA with request-management.view but NOT managing the record, without viewAll -> 403 (AC-030)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertForbidden();
});

it('a user without request-management.view -> 403 (AC-030)', function () {
    $actor = noteActor([]);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertForbidden();
});

it('a user with request-management.viewAll reads notes on ANY record (AC-030)', function () {
    $actor = noteActor(['request-management.view', 'request-management.viewAll']);
    $opportunity = Opportunity::factory()->create();
    // D-9: read access is re-keyed on the Opportunity's own Offerte — an
    // Opportunity with zero Offerte has no `request-management` record to
    // speak of any more (D-1: a row is always a Quote), so it needs at least
    // one (unsupervised is fine: viewAll ignores the supervisor filter).
    Quote::factory()->for($opportunity)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// AC-031 — dedicated write permission, ANDed with read access
// ---------------------------------------------------------------------------

it('read access but no notes.create -> 403 on POST (AC-031)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Hello',
    ])->assertForbidden();
});

it('notes.create but no access to the record -> 403 on POST (AC-031)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Hello',
    ])->assertForbidden();
});

it('both notes.create AND record access -> 201 (AC-031)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Hello',
    ])->assertCreated()
        ->assertJsonPath('data.body', 'Hello')
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('data.can.delete', true);
});

// ---------------------------------------------------------------------------
// AC-032 — author-only mutability, super-admin bypass via Gate::before
// ---------------------------------------------------------------------------

it('the author updates their own note -> 200, edited_at set, body changed (AC-032)', function () {
    $actor = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Original',
    ])->json('data.id');

    $response = $this->patchJson("/api/notes/{$noteId}", ['body' => 'Edited'])->assertOk();

    expect($response->json('data.body'))->toBe('Edited');
    expect($response->json('data.edited_at'))->not->toBeNull();
});

it('another user, even with notes.create and record access, gets 403 on PATCH/DELETE of someone else\'s note (AC-032)', function () {
    $author = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($author);
    Sanctum::actingAs($author);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Original',
    ])->json('data.id');

    // Grant read access via viewAll instead of a second Offerta supervisor
    // (D-9's other branch) — simpler than minting a sibling Offerta just to
    // reach the same read scope.
    $other = noteActor(['request-management.view', 'request-management.viewAll', 'notes.create']);
    Sanctum::actingAs($other);

    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Hacked'])->assertForbidden();
    $this->deleteJson("/api/notes/{$noteId}")->assertForbidden();
});

it('the super-admin updates/deletes a note authored by someone else (Gate::before) (AC-032)', function () {
    $author = noteActor(['request-management.view', 'notes.create']);
    $opportunity = noteManagedOpportunity($author);
    Sanctum::actingAs($author);

    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'request-management',
        'entity_id' => $opportunity->id,
        'body' => 'Original',
    ])->json('data.id');

    // guard_name explicit: an earlier Sanctum::actingAs() in this test has
    // already switched the default auth guard to "sanctum", and spatie
    // resolves an unspecified guard_name from that default — an unqualified
    // Role::create() here would silently create a "sanctum"-guarded role
    // that assignRole() (resolving the User model's own "web" guard) could
    // never find.
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    Sanctum::actingAs($superAdmin);

    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Edited by admin'])->assertOk();
    $this->deleteJson("/api/notes/{$noteId}")->assertOk();
});
