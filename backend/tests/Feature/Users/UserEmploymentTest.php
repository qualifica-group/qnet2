<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Factories\EmploymentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('employmentTestActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function employmentTestActor(array $abilities): User
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

/**
 * A minimal valid individual personal_data block (users.name is derived from
 * it, so every create payload must carry one — unrelated to this feature).
 */
if (! function_exists('employmentTestProfile')) {
    function employmentTestProfile(): array
    {
        return ['type' => 'individual', 'first_name' => 'New', 'last_name' => 'Employee'];
    }
}

// ---------------------------------------------------------------------------
// AC-001 — full employment on create, persisted 1:1, references resolved.
// ---------------------------------------------------------------------------

it('0015 AC-001: create with a full employment block persists the row and resolves {id,label} references', function () {
    $actor = employmentTestActor(['create']);
    $manager = User::factory()->create(['name' => 'Manager One']);
    $function = BusinessFunction::factory()->create(['name' => 'Engineering']);
    $company = Company::factory()->create(['denomination' => 'Acme Srl', 'vat_number' => 'IT123']);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'employee@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => [
            'is_manager' => false,
            'job_description' => 'Backend engineer',
            'reports_to_id' => $manager->id,
            'business_function_id' => $function->id,
            'relationship_type' => 'employee',
            'company_id' => $company->id,
            'primary_operational_site_id' => $site->id,
            'qualification_type' => 'employee_level_5',
            'hired_at' => '2024-01-15',
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
        ],
    ])->assertCreated();

    $created = User::where('email', 'employee@example.com')->first();
    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $created->id,
        'is_manager' => false,
        'reports_to_id' => $manager->id,
        'business_function_id' => $function->id,
        'company_id' => $company->id,
    ]);
    $this->assertDatabaseHas('employment_profile_operational_site', [
        'employment_profile_id' => $created->employment->id,
        'operational_site_id' => $site->id,
        'is_primary' => true,
    ]);

    $response->assertJsonPath('data.employment.reports_to.id', $manager->id)
        ->assertJsonPath('data.employment.reports_to.label', 'Manager One')
        ->assertJsonPath('data.employment.business_function.id', $function->id)
        ->assertJsonPath('data.employment.business_function.label', 'Engineering')
        ->assertJsonPath('data.employment.company.id', $company->id)
        ->assertJsonPath('data.employment.company.label', 'Acme Srl')
        ->assertJsonPath('data.employment.company.subtitle', 'IT123')
        ->assertJsonPath('data.employment.primary_operational_site.id', $site->id);
});

// ---------------------------------------------------------------------------
// AC-002 — employment absent on create leaves no row (back-compat).
// ---------------------------------------------------------------------------

it('0015 AC-002: create without employment persists no employment row', function () {
    $actor = employmentTestActor(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'noemployment@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
    ])->assertCreated()->assertJsonMissingPath('data.employment');

    $created = User::where('email', 'noemployment@example.com')->first();
    $this->assertDatabaseMissing('employment_profiles', ['user_id' => $created->id]);
});

// ---------------------------------------------------------------------------
// AC-003 — is_manager=true forces reports_to_id to null server-side.
// ---------------------------------------------------------------------------

it('0015 AC-003: is_manager=true forces employment.reports_to_id to null', function () {
    $actor = employmentTestActor(['create']);
    $wouldBeManager = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'manager@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => ['is_manager' => true, 'reports_to_id' => $wouldBeManager->id],
    ])->assertCreated();

    $response->assertJsonPath('data.employment.is_manager', true)
        ->assertJsonMissingPath('data.employment.reports_to');

    $created = User::where('email', 'manager@example.com')->first();
    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $created->id,
        'is_manager' => true,
        'reports_to_id' => null,
    ]);
});

// ---------------------------------------------------------------------------
// AC-004 — invalid employment.* => 422 nested keys, no user row (rollback).
// ---------------------------------------------------------------------------

it('0015 AC-004: invalid employment fields reject with nested keys and roll back the whole create', function () {
    $actor = employmentTestActor(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'rollback@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => [
            'reports_to_id' => 999999,
            'primary_operational_site_id' => 999999,
            'qualification_type' => 'not-a-real-type',
            'hired_at' => '2024-06-01',
            'terminated_at' => '2024-01-01',
            'standard_daily_minutes' => 1500,
        ],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.reports_to_id',
        'employment.primary_operational_site_id',
        'employment.qualification_type',
        'employment.terminated_at',
        'employment.standard_daily_minutes',
    ]);

    $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
});

// ---------------------------------------------------------------------------
// AC-005 — update semantics: absent = untouched, null = delete, object = upsert.
// ---------------------------------------------------------------------------

it('0015 AC-005: update with employment absent leaves the row untouched', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->withEmployment()->create();
    $before = $target->employment()->first();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['locale' => 'it'])->assertOk();

    $this->assertDatabaseHas('employment_profiles', ['id' => $before->id, 'user_id' => $target->id]);
});

it('0015 AC-005: update with employment:null deletes the row', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => null])
        ->assertOk()
        ->assertJsonMissingPath('data.employment');

    $this->assertDatabaseMissing('employment_profiles', ['user_id' => $target->id]);
});

it('0015 AC-005: update with an employment object upserts the row', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['is_manager' => false, 'job_description' => 'Updated role'],
    ])->assertOk()->assertJsonPath('data.employment.job_description', 'Updated role');

    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $target->id,
        'job_description' => 'Updated role',
    ]);

    // A second upsert on the SAME row (no duplicate 1:1 row).
    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['is_manager' => false, 'job_description' => 'Updated again'],
    ])->assertOk();

    expect(EmploymentProfile::where('user_id', $target->id)->count())->toBe(1);
    $this->assertDatabaseHas('employment_profiles', ['user_id' => $target->id, 'job_description' => 'Updated again']);
});

// ---------------------------------------------------------------------------
// AC-006 — no self-reference on update.
// ---------------------------------------------------------------------------

it('0015 AC-006: reports_to_id equal to the user being updated is rejected (422)', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['reports_to_id' => $target->id],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.reports_to_id']);

    $this->assertDatabaseMissing('employment_profiles', ['user_id' => $target->id]);
});

// ---------------------------------------------------------------------------
// AC-007 — authz: base users.create/update gates the whole payload; no
// dedicated employment.* permission exists.
// ---------------------------------------------------------------------------

it('0015 AC-007: create with an employment block is 403 without users.create', function () {
    $actor = employmentTestActor([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/users', [
        'email' => 'forbidden@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => ['is_manager' => true],
    ])->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'forbidden@example.com']);
});

it('0015 AC-007: update with an employment block is 403 without users.update', function () {
    $actor = employmentTestActor([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['is_manager' => true]])
        ->assertForbidden();
});

it('0015 AC-007: an actor with only users.create/update (no dedicated employment permission) can write employment', function () {
    $actor = employmentTestActor(['create']);
    Sanctum::actingAs($actor);

    // No `employment.*` permission exists in the system at all — governed by
    // the users.create/update field-permission matrix (spec 0015).
    expect(Permission::where('name', 'like', 'employment%')->exists())->toBeFalse();

    $this->postJson('/api/users', [
        'email' => 'noemploypermission@example.com',
        'locale' => 'en',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => ['is_manager' => true],
    ])->assertCreated()->assertJsonPath('data.employment.is_manager', true);
});

// ---------------------------------------------------------------------------
// Spec 0103 — multi-site membership (physical + remote sites on the
// employment_profile_operational_site pivot). AC-004..AC-008.
// ---------------------------------------------------------------------------

it('0103 AC-004: create with a primary site and two remote sites persists three pivot rows, one primary', function () {
    $actor = employmentTestActor(['create']);
    $primary = OperationalSite::factory()->withAddress()->create();
    $remoteOne = OperationalSite::factory()->withAddress()->create();
    $remoteTwo = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/users', [
        'email' => 'multisite@example.com',
        'locale' => 'it',
        'password' => 'Str0ng-P4ssw0rd!',
        'password_confirmation' => 'Str0ng-P4ssw0rd!',
        'personal_data' => employmentTestProfile(),
        'employment' => [
            'primary_operational_site_id' => $primary->id,
            'remote_operational_site_ids' => [$remoteOne->id, $remoteTwo->id],
        ],
    ])->assertCreated();

    $created = User::where('email', 'multisite@example.com')->first();
    $profileId = $created->employment->id;

    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profileId)->count())->toBe(3);
    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profileId)->where('is_primary', true)->count())->toBe(1);
    $this->assertDatabaseHas('employment_profile_operational_site', [
        'employment_profile_id' => $profileId, 'operational_site_id' => $primary->id, 'is_primary' => true,
    ]);
    foreach ([$remoteOne, $remoteTwo] as $remote) {
        $this->assertDatabaseHas('employment_profile_operational_site', [
            'employment_profile_id' => $profileId, 'operational_site_id' => $remote->id, 'is_primary' => false,
        ]);
    }

    $response->assertJsonPath('data.employment.primary_operational_site_id', $primary->id);
    expect($response->json('data.employment.remote_operational_site_ids'))->toEqualCanonicalizing([$remoteOne->id, $remoteTwo->id]);
});

it('0103 AC-005: switching the primary site from A to B leaves a single primary row on B', function () {
    $actor = employmentTestActor(['update']);
    $siteA = OperationalSite::factory()->withAddress()->create();
    $siteB = OperationalSite::factory()->withAddress()->create();
    $target = User::factory()
        ->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->physicalSite($siteA))
        ->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['primary_operational_site_id' => $siteB->id],
    ])->assertOk();

    $profileId = $target->employment()->first()->id;

    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profileId)->where('is_primary', true)->count())->toBe(1);
    $this->assertDatabaseHas('employment_profile_operational_site', [
        'employment_profile_id' => $profileId, 'operational_site_id' => $siteB->id, 'is_primary' => true,
    ]);
    $this->assertDatabaseMissing('employment_profile_operational_site', [
        'employment_profile_id' => $profileId, 'operational_site_id' => $siteA->id,
    ]);
    $response->assertJsonPath('data.employment.primary_operational_site_id', $siteB->id);
});

it('0103 AC-006: remote_operational_site_ids containing the primary id is rejected (422) with no pivot changes', function () {
    $actor = employmentTestActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $other = OperationalSite::factory()->withAddress()->create();
    $target = User::factory()
        ->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->physicalSite($site)->remoteSites($other))
        ->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => [
            'primary_operational_site_id' => $site->id,
            'remote_operational_site_ids' => [$site->id],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.remote_operational_site_ids.0']);

    $profileId = $target->employment()->first()->id;
    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profileId)->count())->toBe(2);
});

it('0103 AC-007: duplicate ids in remote_operational_site_ids are rejected (422)', function () {
    $actor = employmentTestActor(['update']);
    $target = User::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['remote_operational_site_ids' => [$site->id, $site->id]],
    ])->assertStatus(422)->assertJsonValidationErrors([
        'employment.remote_operational_site_ids.0',
        'employment.remote_operational_site_ids.1',
    ]);

    $this->assertDatabaseMissing('employment_profile_operational_site', ['operational_site_id' => $site->id]);
});

it('0103 AC-008: setting primary_operational_site_id to null clears the physical site and leaves the remotes intact', function () {
    $actor = employmentTestActor(['update']);
    $physical = OperationalSite::factory()->withAddress()->create();
    $remoteOne = OperationalSite::factory()->withAddress()->create();
    $remoteTwo = OperationalSite::factory()->withAddress()->create();
    $target = User::factory()
        ->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->physicalSite($physical)->remoteSites($remoteOne, $remoteTwo))
        ->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['primary_operational_site_id' => null],
    ])->assertOk();

    $profileId = $target->employment()->first()->id;

    $this->assertDatabaseMissing('employment_profile_operational_site', [
        'employment_profile_id' => $profileId, 'operational_site_id' => $physical->id,
    ]);
    foreach ([$remoteOne, $remoteTwo] as $remote) {
        $this->assertDatabaseHas('employment_profile_operational_site', [
            'employment_profile_id' => $profileId, 'operational_site_id' => $remote->id, 'is_primary' => false,
        ]);
    }

    $response->assertJsonPath('data.employment.primary_operational_site_id', null);
    expect($response->json('data.employment.remote_operational_site_ids'))->toEqualCanonicalizing([$remoteOne->id, $remoteTwo->id]);
});
