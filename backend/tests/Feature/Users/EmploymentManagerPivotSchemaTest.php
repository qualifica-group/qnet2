<?php

use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Spec 0166 (D-2): the `employment_profile_manager` pivot cascades on either
 * side, no application code involved yet — pure schema coverage of the
 * migration, via the query builder. Mirrors
 * EmploymentSiteMembershipSchemaTest.php (spec 0103 M1).
 */
it('deleting the manager cascades the pivot row and leaves the employment profile intact', function () {
    $profile = EmploymentProfile::factory()->create();
    $manager = User::factory()->create();

    DB::table('employment_profile_manager')->insert([
        'employment_profile_id' => $profile->id,
        'user_id' => $manager->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manager->delete();

    expect(DB::table('employment_profile_manager')->where('employment_profile_id', $profile->id)->exists())
        ->toBeFalse();
    expect(EmploymentProfile::query()->whereKey($profile->id)->exists())->toBeTrue();
});

it('deleting the employment profile cascades the pivot row', function () {
    $profile = EmploymentProfile::factory()->create();
    $manager = User::factory()->create();

    DB::table('employment_profile_manager')->insert([
        'employment_profile_id' => $profile->id,
        'user_id' => $manager->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $profile->delete();

    expect(DB::table('employment_profile_manager')->where('user_id', $manager->id)->exists())
        ->toBeFalse();
    expect(User::query()->whereKey($manager->id)->exists())->toBeTrue();
});

it('the same (profile, manager) pair cannot be inserted twice', function () {
    $profile = EmploymentProfile::factory()->create();
    $manager = User::factory()->create();

    DB::table('employment_profile_manager')->insert([
        'employment_profile_id' => $profile->id,
        'user_id' => $manager->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('employment_profile_manager')->insert([
        'employment_profile_id' => $profile->id,
        'user_id' => $manager->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
