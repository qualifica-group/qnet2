<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Feature coverage for AC-012 (spec 0015): UsersAuthorization::fields()
 * includes the 12 `employment.*` keys; a role with a field denied in the
 * matrix cannot write it (ceiling respected), and pre-existing values are
 * preserved (CHANGE-based enforcement, spec 0008).
 */
const EMPLOYMENT_FIELD_KEYS = [
    'employment.is_manager', 'employment.job_description', 'employment.reports_to_id',
    'employment.business_function_id', 'employment.relationship_type', 'employment.company_id',
    'employment.primary_operational_site_id', 'employment.remote_operational_site_ids',
    'employment.qualification_type', 'employment.hired_at',
    'employment.terminated_at', 'employment.standard_daily_minutes', 'employment.break_daily_minutes',
];

if (! function_exists('employmentFieldPermActor')) {
    function employmentFieldPermActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

it('AC-012: permissions.fields includes the 12 employment.* keys, editable when the actor may update', function () {
    $actor = employmentFieldPermActor(['view', 'update']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    foreach (EMPLOYMENT_FIELD_KEYS as $key) {
        expect($fields)->toHaveKey($key);
        expect($fields[$key]['visible'])->toBeTrue()
            ->and($fields[$key]['editable'])->toBeTrue();
    }
});

it('AC-012: employment.* fields are visibleReadonly when the actor may NOT update', function () {
    $actor = employmentFieldPermActor(['view']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    expect($fields['employment.job_description']['editable'])->toBeFalse()
        ->and($fields['employment.job_description']['readonly'])->toBeTrue();
});

it('AC-012: a role denying employment.job_description in the matrix cannot write it, and the existing value is preserved', function () {
    Permission::findOrCreate('users.view');
    Permission::findOrCreate('users.update');

    $role = Role::create(['name' => 'employment-field-perm-'.uniqid()]);
    $role->givePermissionTo(['users.view', 'users.update']);
    $role->fieldPermissions()->create([
        'resource' => 'users', 'field' => 'employment.job_description', 'visible' => true, 'editable' => false, 'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = User::factory()->withEmployment(fn ($f) => $f->state(['job_description' => 'Original role']))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['job_description' => 'Changed role'],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.job_description']);

    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $target->id,
        'job_description' => 'Original role',
    ]);
});

it('AC-012: no employment.* resource permission exists in the system — governed entirely by the field matrix', function () {
    expect(Permission::where('name', 'like', 'employment.%')->exists())->toBeFalse();
});

/**
 * An actor whose role denies $field (visible/readonly) on `users` — same
 * shape as the job_description role above, reused for the two site keys
 * spec 0103 (D-9) split `employment.operational_site_id` into.
 */
if (! function_exists('roleDenyingEmploymentField')) {
    function roleDenyingEmploymentField(string $field): User
    {
        Permission::findOrCreate('users.view');
        Permission::findOrCreate('users.update');

        $role = Role::create(['name' => 'employment-field-perm-'.uniqid()]);
        $role->givePermissionTo(['users.view', 'users.update']);
        $role->fieldPermissions()->create([
            'resource' => 'users', 'field' => $field, 'visible' => true, 'editable' => false, 'required' => false,
        ]);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-013 — employment.primary_operational_site_id readonly: resubmitting the
// SAME physical site is a no-op (200); a real change 422s (spec 0103 D-9).
// ---------------------------------------------------------------------------

it('AC-013: resubmitting the same physical site on a readonly employment.primary_operational_site_id is a no-op (200)', function () {
    $siteA = OperationalSite::factory()->create();
    $actor = roleDenyingEmploymentField('employment.primary_operational_site_id');
    $target = User::factory()->withEmployment(fn ($f) => $f->physicalSite($siteA))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['primary_operational_site_id' => $siteA->id],
    ])->assertOk();
});

it('AC-013: changing the physical site on a readonly employment.primary_operational_site_id 422s', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $actor = roleDenyingEmploymentField('employment.primary_operational_site_id');
    $target = User::factory()->withEmployment(fn ($f) => $f->physicalSite($siteA))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['primary_operational_site_id' => $siteB->id],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.primary_operational_site_id']);
});

// ---------------------------------------------------------------------------
// AC-014 — employment.remote_operational_site_ids readonly: resubmitting the
// SAME set in a DIFFERENT order is a no-op (200); a real set change 422s.
// ---------------------------------------------------------------------------

it('AC-014: resubmitting the same remote site set in a different order on a readonly employment.remote_operational_site_ids is a no-op (200)', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $actor = roleDenyingEmploymentField('employment.remote_operational_site_ids');
    $target = User::factory()->withEmployment(fn ($f) => $f->remoteSites($siteA, $siteB))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        // Reversed order versus creation (siteA then siteB): the comparison
        // must be order-insensitive (spec 0008 AC-007 normalization).
        'employment' => ['remote_operational_site_ids' => [$siteB->id, $siteA->id]],
    ])->assertOk();
});

it('AC-014: changing the remote site set on a readonly employment.remote_operational_site_ids 422s', function () {
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $siteC = OperationalSite::factory()->create();
    $actor = roleDenyingEmploymentField('employment.remote_operational_site_ids');
    $target = User::factory()->withEmployment(fn ($f) => $f->remoteSites($siteA, $siteB))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['remote_operational_site_ids' => [$siteA->id, $siteC->id]],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.remote_operational_site_ids']);
});

// ---------------------------------------------------------------------------
// Lazy-loading (spec 0103 D-9 note, verified not just assumed):
// EmploymentProfile::primaryOperationalSiteId()/remoteOperationalSiteIds()
// read $this->operationalSites — a relation the route-model-bound $user in
// Update/StoreUserRequest never eager-loads, so this DOES lazy-load on the
// single/write path. It stays harmless even under Laravel's
// Model::preventLazyLoading() (which nothing in this codebase currently
// wires up globally — verified: no AppServiceProvider::boot() call, only
// the per-test opt-in used below and elsewhere, e.g. ProductTableTest):
// Illuminate\Database\Eloquent\Builder::hydrate() only stamps the
// instance-level guard when count($items) > 1 — i.e. the guard exists
// specifically to catch an N+1 across a COLLECTION, never a single
// find()/route-bound model or its to-one/hasOne relation. A single
// GET/PATCH /api/users/{id} is exactly that single-row case, so this
// accessor's lazy load can never trip the guard here even once it is wired
// up — the real N+1 risk for `operationalSites` lives in a GRID that lists
// many users (UsersTableDefinition/UserEmploymentColumns, a different
// lane's surface), not in this single-record read/write path.
// ---------------------------------------------------------------------------

it('the field-permission read of employment.primary_operational_site_id lazy-loads operationalSites but never trips preventLazyLoading on a single record', function () {
    $siteA = OperationalSite::factory()->create();
    $actor = roleDenyingEmploymentField('employment.primary_operational_site_id');
    $target = User::factory()->withEmployment(fn ($f) => $f->physicalSite($siteA))->create();
    Sanctum::actingAs($actor);

    EmploymentProfile::preventLazyLoading();

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['primary_operational_site_id' => $siteA->id],
    ]);

    EmploymentProfile::preventLazyLoading(false);

    $response->assertOk();
});

// ---------------------------------------------------------------------------
// AC-015 — the split migration turns one employment.operational_site_id row
// into two (same flags), and the original key is gone.
// ---------------------------------------------------------------------------

it('AC-015: the migration splits an employment.operational_site_id row into the two new keys, same flags, and back', function () {
    $role = Role::create(['name' => 'field-perm-split-role-'.uniqid()]);
    $migration = require database_path('migrations/2026_09_07_100200_split_employment_operational_site_field_permission.php');

    DB::table('role_field_permissions')->insert([
        'role_id' => $role->id, 'resource' => 'users', 'field' => 'employment.operational_site_id',
        'visible' => true, 'editable' => false, 'required' => false,
    ]);

    $migration->up();

    $rows = DB::table('role_field_permissions')->where('role_id', $role->id)->get()->keyBy('field');
    expect($rows->keys()->all())->toEqualCanonicalizing([
        'employment.primary_operational_site_id', 'employment.remote_operational_site_ids',
    ]);

    foreach ($rows as $row) {
        expect((bool) $row->visible)->toBeTrue()
            ->and((bool) $row->editable)->toBeFalse()
            ->and((bool) $row->required)->toBeFalse();
    }

    $migration->down();

    $collapsed = DB::table('role_field_permissions')->where('role_id', $role->id)->sole();
    expect($collapsed->field)->toBe('employment.operational_site_id');
});

it('AC-015: a pre-existing row on one of the new keys does not break the split, the configured one wins', function () {
    $role = Role::create(['name' => 'field-perm-split-conflict-'.uniqid()]);
    $migration = require database_path('migrations/2026_09_07_100200_split_employment_operational_site_field_permission.php');

    DB::table('role_field_permissions')->insert([
        ['role_id' => $role->id, 'resource' => 'users', 'field' => 'employment.operational_site_id', 'visible' => true, 'editable' => false, 'required' => false],
        ['role_id' => $role->id, 'resource' => 'users', 'field' => 'employment.primary_operational_site_id', 'visible' => true, 'editable' => true, 'required' => false],
    ]);

    $migration->up();

    $rows = DB::table('role_field_permissions')->where('role_id', $role->id)->get()->keyBy('field');
    expect($rows->keys()->all())->toEqualCanonicalizing([
        'employment.primary_operational_site_id', 'employment.remote_operational_site_ids',
    ]);
    expect((bool) $rows['employment.primary_operational_site_id']->editable)->toBeFalse();
});
