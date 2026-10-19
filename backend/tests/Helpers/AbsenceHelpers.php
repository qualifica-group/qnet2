<?php

declare(strict_types=1);

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('absenceUserWith')) {
    /**
     * A user holding exactly the given `absence-requests` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function absenceUserWith(array $abilities = ['viewAny', 'create']): User
    {
        foreach (['viewAny', 'create', 'manageAll'] as $ability) {
            Permission::findOrCreate("absence-requests.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("absence-requests.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('absenceProfile')) {
    /**
     * The employment profile of a person (created on demand), reporting to the
     * given managers, with a 480-minute day and no break unless overridden.
     *
     * @param  array<string, mixed>  $attributes
     */
    function absenceProfile(User $person, ?User $manager = null, array $attributes = []): EmploymentProfile
    {
        $profile = EmploymentProfile::query()->where('user_id', $person->id)->first()
            ?? EmploymentProfile::factory()->create(['user_id' => $person->id, 'standard_daily_minutes' => 480, 'break_daily_minutes' => 0, ...$attributes]);

        if ($manager !== null) {
            $profile->reportsTo()->syncWithoutDetaching([$manager->id]);
        }

        return $profile;
    }
}

if (! function_exists('absencePayload')) {
    /**
     * A valid full-day write payload (Mon 2026-12-14), on a fresh absence type unless given.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function absencePayload(array $overrides = []): array
    {
        return [
            'absence_type_id' => AbsenceType::factory()->create()->id,
            'is_full_day' => true,
            'starts_on' => '2026-12-14',
            'ends_on' => '2026-12-14',
            ...$overrides,
        ];
    }
}

if (! function_exists('absenceSubmitted')) {
    /**
     * A persisted submitted request of `$person` approved by `$approver` (snapshot on the pivot).
     *
     * @param  array<string, mixed>  $attributes
     */
    function absenceSubmitted(User $person, User $approver, array $attributes = []): AbsenceRequest
    {
        $request = AbsenceRequest::factory()->forUser($person)->submitted()->create([
            'absence_type_id' => AbsenceType::factory()->create()->id,
            'starts_on' => '2026-12-14',
            'ends_on' => '2026-12-14',
            ...$attributes,
        ]);
        $request->approvers()->attach($approver->id);

        return $request;
    }
}
