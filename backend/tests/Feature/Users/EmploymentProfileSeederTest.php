<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
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

it('fills the company FK and the site membership pivot from the seeded lookups', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    Company::factory()->count(4)->create();
    OperationalSite::factory()->count(4)->create();

    $this->seed(DemoEmploymentProfileSeeder::class);

    // The FK is present ~75% of the time; across every seeded profile the
    // column is reliably non-empty.
    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBeGreaterThanOrEqual(1);

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

it('leaves the company FK, the site membership and the competence rows empty when no lookups are seeded', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBe(0);
    expect(EmploymentProfile::has('operationalSites')->count())->toBe(0);
    expect(EmploymentProfile::has('productLines')->count())->toBe(0);
});

/**
 * Spec 0111: the competence is a collection of (function, category) rows, and
 * the seeder must only ever pair a category with its EFFECTIVE business
 * function — anything else is data the user form would refuse to save.
 */
it('seeds competence rows pairing each category with its effective business function', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $functions = BusinessFunction::factory()->count(2)->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $functions->first()->id]);
    // An inheriting child (spec 0023): its row must carry the ROOT's function.
    ProductCategory::factory()->childOf($root)->create();
    ProductCategory::factory()->create(['business_function_id' => $functions->last()->id]);

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::has('productLines')->count())->toBeGreaterThanOrEqual(1);

    $expectedFunctionByCategory = ProductCategory::all()
        ->mapWithKeys(fn (ProductCategory $category): array => [
            $category->id => $category->business_function_id ?? $root->business_function_id,
        ]);

    EmploymentProductLine::all()->each(function (EmploymentProductLine $line) use ($expectedFunctionByCategory): void {
        expect($line->business_function_id)->toBe($expectedFunctionByCategory[$line->product_category_id]);
    });
});

it('does not seed competence rows on categories that are not selectable', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $function = BusinessFunction::factory()->create();
    $hidden = ProductCategory::factory()->create([
        'business_function_id' => $function->id,
        'is_selectable' => false,
    ]);
    ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProductLine::where('product_category_id', $hidden->id)->exists())->toBeFalse()
        ->and(EmploymentProductLine::count())->toBeGreaterThanOrEqual(1);
});

it('is idempotent — re-running does not duplicate employment rows, site memberships nor competence rows', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    OperationalSite::factory()->count(4)->create();
    ProductCategory::factory()->count(3)->create([
        'business_function_id' => BusinessFunction::factory()->create()->id,
    ]);
    $this->seed(DemoEmploymentProfileSeeder::class);
    $countBefore = EmploymentProfile::count();
    $membershipsBefore = DB::table('employment_profile_operational_site')->count();
    $linesBefore = EmploymentProductLine::count();

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::count())->toBe($countBefore);
    expect(DB::table('employment_profile_operational_site')->count())->toBe($membershipsBefore);
    expect(EmploymentProductLine::count())->toBe($linesBefore);
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

it('EmploymentProfileFactory::competentIn() attaches one competence row per category, all on the given function', function () {
    $function = BusinessFunction::factory()->create();
    $first = ProductCategory::factory()->create();
    $second = ProductCategory::factory()->create();

    $employment = EmploymentProfile::factory()->competentIn($function, $first, $second)->create();

    expect($employment->productLines()->pluck('product_category_id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id])
        ->and($employment->productLines()->pluck('business_function_id')->unique()->all())
        ->toBe([$function->id]);
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
