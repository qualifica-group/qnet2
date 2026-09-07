<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Seeders\DemoEmploymentProfileSeeder;
use Database\Seeders\DemoRolesSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Feature coverage for AC-013 (spec 0015): EmploymentProfileFactory states +
 * the seeder's manager/subordinate hierarchy.
 */

// ---------------------------------------------------------------------------
// Seeder
// ---------------------------------------------------------------------------

it('seeds at least 2 managers and every other seeded user reports to one of them (no self-reference)', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $this->seed(DemoEmploymentProfileSeeder::class);

    $managers = EmploymentProfile::where('is_manager', true)->get();
    expect($managers->count())->toBeGreaterThanOrEqual(2);

    foreach ($managers as $manager) {
        expect($manager->reports_to_id)->toBeNull();
    }

    $managerUserIds = $managers->pluck('user_id')->all();

    $subordinates = EmploymentProfile::where('is_manager', false)->get();
    expect($subordinates->count())->toBeGreaterThanOrEqual(1);

    foreach ($subordinates as $subordinate) {
        expect($subordinate->reports_to_id)->not->toBeNull()
            ->and($subordinate->reports_to_id)->toBeIn($managerUserIds)
            ->and($subordinate->reports_to_id)->not->toBe($subordinate->user_id);
    }
});

it('fills the contractual FKs (function/company) and the site membership pivot from the seeded lookups', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    BusinessFunction::factory()->count(4)->create();
    Company::factory()->count(4)->create();
    OperationalSite::factory()->count(4)->create();

    $this->seed(DemoEmploymentProfileSeeder::class);

    // Each FK is present ~75% of the time; across every seeded profile the
    // two columns are reliably non-empty.
    expect(EmploymentProfile::whereNotNull('business_function_id')->count())->toBeGreaterThanOrEqual(1);
    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBeGreaterThanOrEqual(1);

    // Every assigned FK points at a real seeded row.
    $businessFunctionIds = BusinessFunction::pluck('id')->all();
    EmploymentProfile::whereNotNull('business_function_id')->pluck('business_function_id')
        ->each(fn (int $id) => expect($id)->toBeIn($businessFunctionIds));

    // Spec 0103: the site membership lives on the pivot, at most one
    // physical row per profile, plus some remote ones exercising D-1.
    expect(EmploymentProfile::has('operationalSites')->count())->toBeGreaterThanOrEqual(1);
    expect(EmploymentProfile::has('primaryOperationalSite')->count())->toBeGreaterThanOrEqual(1);
    expect(EmploymentProfile::has('remoteOperationalSites')->count())->toBeGreaterThanOrEqual(1);

    $operationalSiteIds = OperationalSite::pluck('id')->all();
    EmploymentProfile::with('operationalSites')->get()
        ->each(function (EmploymentProfile $profile) use ($operationalSiteIds): void {
            expect($profile->operationalSites->pluck('id')->all())->each->toBeIn($operationalSiteIds);
            expect($profile->operationalSites->where('pivot.is_primary', true))->toHaveCount(
                $profile->primaryOperationalSiteId === null ? 0 : 1
            );
        });
});

it('leaves the contractual FKs and the site membership empty when no lookups are seeded', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::whereNotNull('business_function_id')->count())->toBe(0);
    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBe(0);
    expect(EmploymentProfile::has('operationalSites')->count())->toBe(0);
});

it('is idempotent — re-running does not duplicate employment rows nor site memberships', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    OperationalSite::factory()->count(4)->create();
    $this->seed(DemoEmploymentProfileSeeder::class);
    $countBefore = EmploymentProfile::count();
    $membershipsBefore = DB::table('employment_profile_operational_site')->count();

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::count())->toBe($countBefore);
    expect(DB::table('employment_profile_operational_site')->count())->toBe($membershipsBefore);
});

// ---------------------------------------------------------------------------
// Factory states
// ---------------------------------------------------------------------------

it('EmploymentProfileFactory::manager() forces is_manager true and reports_to null', function () {
    $employment = EmploymentProfile::factory()->manager()->create();

    expect($employment->is_manager)->toBeTrue()
        ->and($employment->reports_to_id)->toBeNull();
});

it('EmploymentProfileFactory::reportsTo() points to the given manager', function () {
    $manager = User::factory()->create();

    $employment = EmploymentProfile::factory()->reportsTo($manager)->create();

    expect($employment->is_manager)->toBeFalse()
        ->and($employment->reports_to_id)->toBe($manager->id);
});

it('UserFactory::withEmployment()/manager()/reportsTo() attach an employment profile', function () {
    $manager = User::factory()->manager()->create();
    expect($manager->employment)->not->toBeNull()
        ->and($manager->employment->is_manager)->toBeTrue();

    $subordinate = User::factory()->reportsTo($manager)->create();
    expect($subordinate->employment->reports_to_id)->toBe($manager->id);

    $plain = User::factory()->withEmployment()->create();
    expect($plain->employment)->not->toBeNull();
});

it('EmploymentProfileFactory::physicalSite() attaches the given site as is_primary', function () {
    $site = OperationalSite::factory()->create();

    $employment = EmploymentProfile::factory()->physicalSite($site)->create();

    expect($employment->primaryOperationalSiteId)->toBe($site->id)
        ->and($employment->remoteOperationalSiteIds)->toBe([]);
});

it('EmploymentProfileFactory::remoteSites() attaches the given sites as non-primary', function () {
    $remoteA = OperationalSite::factory()->create();
    $remoteB = OperationalSite::factory()->create();

    $employment = EmploymentProfile::factory()->remoteSites($remoteA, $remoteB)->create();

    expect($employment->primaryOperationalSiteId)->toBeNull()
        ->and($employment->remoteOperationalSiteIds)->toEqualCanonicalizing([$remoteA->id, $remoteB->id]);
});

it('EmploymentProfileFactory combines physicalSite() and remoteSites() on the same profile', function () {
    $physical = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();

    $employment = EmploymentProfile::factory()->physicalSite($physical)->remoteSites($remote)->create();

    expect($employment->primaryOperationalSiteId)->toBe($physical->id)
        ->and($employment->remoteOperationalSiteIds)->toBe([$remote->id]);
});
