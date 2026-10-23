<?php

declare(strict_types=1);

use App\Models\EmploymentProfile;
use App\Models\JobRole;
use App\Models\StaffCourse;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('trainingUserWith')) {
    /**
     * A user holding exactly the given `staff-trainings` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function trainingUserWith(array $abilities = ['viewAny'], array $attributes = []): User
    {
        foreach (['viewAny', 'viewAll', 'manage'] as $ability) {
            Permission::findOrCreate("staff-trainings.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create($attributes);

        foreach ($abilities as $ability) {
            $user->givePermissionTo("staff-trainings.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('trainingProfile')) {
    /**
     * The employment profile of a person (created on demand), reporting to the
     * given manager and holding the given job roles.
     *
     * @param  array<int, JobRole>  $jobRoles
     */
    function trainingProfile(User $person, ?User $manager = null, array $jobRoles = []): EmploymentProfile
    {
        $profile = EmploymentProfile::query()->where('user_id', $person->id)->first()
            ?? EmploymentProfile::factory()->create(['user_id' => $person->id]);

        if ($manager !== null) {
            $profile->reportsTo()->syncWithoutDetaching([$manager->id]);
        }

        if ($jobRoles !== []) {
            $profile->jobRoles()->syncWithoutDetaching(array_map(static fn (JobRole $role): int => $role->id, $jobRoles));
        }

        return $profile;
    }
}

if (! function_exists('trainingRequiredBy')) {
    /**
     * A job role requiring the given courses.
     *
     * @param  array<int, StaffCourse>  $courses
     */
    function trainingRequiredBy(array $courses): JobRole
    {
        $role = JobRole::factory()->create();

        foreach ($courses as $course) {
            $course->jobRoles()->attach($role->id);
        }

        return $role;
    }
}

if (! function_exists('trainingPayload')) {
    /**
     * A valid write payload on a fresh course unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function trainingPayload(User $person, array $overrides = []): array
    {
        return [
            'user_id' => $person->id,
            'staff_course_id' => StaffCourse::factory()->create()->id,
            'completed_on' => now()->subDays(10)->format('Y-m-d'),
            'outcome' => 'passed',
            ...$overrides,
        ];
    }
}
