<?php

use App\Models\Campaign;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/{domain}/rows/{row} — generic inline cell-editing engine
// (spec 0053). Exercised mainly on `opportunities` (4 editable scalar
// columns: estimated_value/success_probability/start_date/
// expected_close_date — spec 0057, D-5 removed `name`, now server-derived and
// non-editable); `request-management` covers the domain-scoped 404 (AC-009);
// `sectors` covers a domain with zero editable columns (AC-016).

uses(RefreshDatabase::class);

if (! function_exists('inlineEditActor')) {
    /**
     * A direct-permission actor (no role), for tests that don't need the
     * role_field_permissions DB matrix.
     *
     * @param  array<int, string>  $abilities
     */
    function inlineEditActor(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('inlineEditActorWithRole')) {
    /**
     * A role-bearing actor, optionally carrying one role_field_permissions
     * row — the DB matrix only ever restricts actors reached through a role
     * (FieldPermissionRepository reads $actor->roles), never a bare
     * direct-permission grant.
     *
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>|null  $matrixRow
     */
    function inlineEditActorWithRole(string $resource, array $abilities, ?array $matrixRow = null, ?string $roleName = null): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $role = Role::create(['name' => $roleName ?? 'inline-edit-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "{$resource}.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — happy path
// ---------------------------------------------------------------------------

it('AC-001: PATCH a declared-editable column -> 200, persisted, full re-mapped row', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create(['estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => 200,
    ])->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $opportunity->id)
        ->assertJsonPath('data.estimated_value', '200.00')
        ->assertJsonPath('data.editable', true);

    expect($response->json('data.actions'))->toBeArray();
    expect((float) $opportunity->fresh()->estimated_value)->toBe(200.0);
});

// ---------------------------------------------------------------------------
// AC-002 — no {resource}.update ability
// ---------------------------------------------------------------------------

it('AC-002: without opportunities.update -> 403, no DB write', function () {
    $actor = inlineEditActor('opportunities', ['viewAny']);
    $opportunity = Opportunity::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => 'Hacked',
    ])->assertForbidden();

    expect($opportunity->fresh()->name)->toBe('Untouched');
});

// ---------------------------------------------------------------------------
// AC-003 — role_field_permissions.editable = false
// ---------------------------------------------------------------------------

it('AC-003: a DB field-permission row denying `estimated_value` -> 403 on PATCH, and GET columns emits editable:false', function () {
    // `name` is a MANDATORY field (spec 0008): its ceiling can never be
    // narrowed by the DB matrix, so this targets the non-mandatory
    // `estimated_value` instead, mirroring FieldPermissionMergeTest's own
    // retargeting for the same reason.
    $actor = inlineEditActorWithRole(
        'opportunities',
        ['viewAny', 'update'],
        ['resource' => 'opportunities', 'field' => 'estimated_value', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $opportunity = Opportunity::factory()->create(['estimated_value' => 42]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => 999,
    ])->assertForbidden();

    expect((float) $opportunity->fresh()->estimated_value)->toBe(42.0);

    $columns = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['estimated_value']['editable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-004 — row not in baseQuery() scope
// ---------------------------------------------------------------------------

it('AC-004: a non-existent row id -> 404, no DB write, existence not confirmed', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/tables/opportunities/rows/999999999', [
        'column' => 'name',
        'value' => 'Ghost',
    ])->assertNotFound();
});

// Regression (verifier-reported RED, spec 0150): {row} widened from `int` to
// `string` (TableController::updateRow) so a uuid-keyed domain
// (`notifications`) no longer 500s during route-argument binding. A
// non-numeric id on an INT-keyed domain must still resolve to a clean 404 —
// never a 500 — through the exact same `baseQuery()->findOrFail()` path.
it('a non-numeric row id on an int-keyed domain -> 404 with the envelope, never a 500', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/tables/opportunities/rows/not-a-number', [
        'column' => 'name',
        'value' => 'Ghost',
    ])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

// ---------------------------------------------------------------------------
// AC-005 / AC-006 — column allow-list
// ---------------------------------------------------------------------------

it('AC-005: a column that exists but is not declared editable -> 422, no DB write', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'created_at', // sortable/filterable, but never editable
        'value' => now()->toDateString(),
    ])->assertStatus(422);
});

it('AC-006: an arbitrary/unknown column id -> 422, no DB write, no SQL error', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'DROP TABLE opportunities;--',
        'value' => 'x',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-007 — no viewAny
// ---------------------------------------------------------------------------

it('AC-007: without opportunities.viewAny -> 403', function () {
    $actor = inlineEditActor('opportunities', ['update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => 'x',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-008 — super-admin bypasses the field matrix, still subject to authorizeUpdate
// ---------------------------------------------------------------------------

it('AC-008: a super-admin bypasses a restrictive field-permission row', function () {
    $role = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities', 'field' => 'estimated_value', 'visible' => true, 'editable' => false, 'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

    $opportunity = Opportunity::factory()->create(['estimated_value' => 42]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => 999,
    ])->assertOk();

    expect((float) $opportunity->fresh()->estimated_value)->toBe(999.0);
});

// ---------------------------------------------------------------------------
// AC-009 — request-management's own scope (GA2 operator / viewAll)
// ---------------------------------------------------------------------------

it('AC-009: request-management operator not managing the record and without viewAll -> 404', function () {
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.update']);

    $opportunity = Opportunity::factory()->create();
    $otherManager = User::factory()->create();
    $opportunity->managers()->sync([$otherManager->id => ['position' => 2]]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => 'x',
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-010 — value validation derived from the column's type
// ---------------------------------------------------------------------------

it('AC-010: a non-numeric value on a number column -> 422, no DB write', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create(['estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => 'not-a-number',
    ])->assertStatus(422);

    expect((float) $opportunity->fresh()->estimated_value)->toBe(100.0);
});

it('AC-010: an unparsable date on a date column -> 422', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'start_date',
        'value' => 'not-a-date',
    ])->assertStatus(422);
});

it('AC-010: a value outside the column\'s own extra rules (success_probability out of 0-100) -> 422', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'success_probability',
        'value' => 150,
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-011 — null handling
// ---------------------------------------------------------------------------

// Spec 0057, D-5 removed `opportunities.name` (this test's former example): it
// is server-derived and no longer editable at all, so it can no longer stand
// for "a non-nullable, editable column" — no other opportunities scalar is
// both. `campaigns.name` (CampaignColumnCatalog) is the same shape elsewhere
// in the generic engine: real, NOT NULL column, `editable: true`, no
// `nullable` key — the engine itself is domain-agnostic, so this AC does not
// need to run on opportunities specifically.
it('AC-011: value:null on a non-nullable column -> 422', function () {
    $actor = inlineEditActor('campaigns', ['viewAny', 'update']);
    $campaign = Campaign::factory()->create(['name' => 'Kept']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/campaigns/rows/{$campaign->id}", [
        'column' => 'name',
        'value' => null,
    ])->assertStatus(422);

    expect($campaign->fresh()->name)->toBe('Kept');
});

it('AC-011: value:null on a nullable column -> 200, NULL persisted', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create(['estimated_value' => 250]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => null,
    ])->assertOk();

    expect($opportunity->fresh()->estimated_value)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-014 — user preferences cannot alter `editable`
// ---------------------------------------------------------------------------

it('AC-014: saving preferences with an `editable` key does not change the resolved config', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $before = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['estimated_value']['editable'];

    $this->postJson('/api/tables/opportunities/preferences', [
        'columns' => [
            ['id' => 'estimated_value', 'visible' => true, 'width' => 200, 'order' => 1, 'editable' => false],
        ],
    ])->assertOk();

    $after = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['estimated_value']['editable'];

    expect($before)->toBeTrue()->and($after)->toBe($before);
});

// ---------------------------------------------------------------------------
// AC-015 — audit
// ---------------------------------------------------------------------------

it('AC-015: a successful PATCH writes an activity-log entry for the changed field', function () {
    $actor = inlineEditActor('opportunities', ['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create(['estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => 200,
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($actor->id);
    expect($activity->properties->get('attributes'))->toHaveKey('estimated_value', '200.00');
});

// ---------------------------------------------------------------------------
// AC-016 — a domain with zero editable columns never changes behaviour
// ---------------------------------------------------------------------------

it('AC-016: PATCH on a domain with no editable column -> 422 regardless of the column submitted', function () {
    Permission::findOrCreate('sectors.viewAny');
    Permission::findOrCreate('sectors.update');

    $actor = User::factory()->create();
    $actor->givePermissionTo(['sectors.viewAny', 'sectors.update']);

    $sector = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/sectors/rows/{$sector->id}", [
        'column' => 'name', // a real, sortable/filterable column — just never editable
        'value' => 'Should not persist',
    ])->assertStatus(422);
});
