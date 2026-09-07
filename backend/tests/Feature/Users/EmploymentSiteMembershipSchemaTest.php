<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0103 (M1) AC-021: the `employment_profile_operational_site` pivot
 * cascades on either side, no application code involved yet — this is pure
 * schema coverage of the migration, via the query builder (no Eloquent
 * relation exists on EmploymentProfile until a later microtask).
 */
it('AC-021: deleting the operational site cascades the pivot row and leaves the employment profile intact', function () {
    $profile = EmploymentProfile::factory()->create();
    $site = OperationalSite::factory()->create();

    DB::table('employment_profile_operational_site')->insert([
        'employment_profile_id' => $profile->id,
        'operational_site_id' => $site->id,
        'is_primary' => true,
    ]);

    $site->delete();

    expect(DB::table('employment_profile_operational_site')->where('employment_profile_id', $profile->id)->exists())
        ->toBeFalse();
    expect(EmploymentProfile::query()->whereKey($profile->id)->exists())->toBeTrue();
});

it('deleting the employment profile cascades the pivot row', function () {
    $profile = EmploymentProfile::factory()->create();
    $site = OperationalSite::factory()->create();

    DB::table('employment_profile_operational_site')->insert([
        'employment_profile_id' => $profile->id,
        'operational_site_id' => $site->id,
        'is_primary' => false,
    ]);

    $profile->delete();

    expect(DB::table('employment_profile_operational_site')->where('operational_site_id', $site->id)->exists())
        ->toBeFalse();
    expect(OperationalSite::query()->whereKey($site->id)->exists())->toBeTrue();
});
