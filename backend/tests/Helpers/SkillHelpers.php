<?php

declare(strict_types=1);

use App\Models\EmploymentProfile;
use App\Models\JobRole;
use App\Models\Skill;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('skillUserWith')) {
    /**
     * A user holding exactly the given `user-skills` abilities.
     *
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>  $attributes
     */
    function skillUserWith(array $abilities = ['viewAny'], array $attributes = []): User
    {
        foreach (['viewAny', 'viewAll', 'manage'] as $ability) {
            Permission::findOrCreate("user-skills.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create($attributes);

        foreach ($abilities as $ability) {
            $user->givePermissionTo("user-skills.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('skillProfile')) {
    /**
     * The employment profile of a person (created on demand), reporting to the
     * given manager and holding the given job roles.
     *
     * @param  array<int, JobRole>  $jobRoles
     */
    function skillProfile(User $person, ?User $manager = null, array $jobRoles = []): EmploymentProfile
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

if (! function_exists('skillRequiredBy')) {
    /**
     * A job role requiring each given skill at the given minimum level.
     *
     * @param  array<int, array{0: Skill, 1: int}>  $requirements  [skill, min level] pairs
     */
    function skillRequiredBy(array $requirements): JobRole
    {
        $role = JobRole::factory()->create();

        foreach ($requirements as [$skill, $level]) {
            $skill->jobRoles()->attach($role->id, ['min_level' => $level]);
        }

        return $role;
    }
}

if (! function_exists('userSkillPayload')) {
    /**
     * A valid write payload on a fresh skill unless overridden.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function userSkillPayload(User $person, array $overrides = []): array
    {
        return [
            'user_id' => $person->id,
            'skill_id' => Skill::factory()->create()->id,
            'level' => 2,
            'assessed_on' => now()->subDays(3)->format('Y-m-d'),
            ...$overrides,
        ];
    }
}
