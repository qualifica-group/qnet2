<?php

use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Rule R — TimeEntryReadAuthorizer (spec 0122, data_contract, AC-016)
|--------------------------------------------------------------------------
| Exercised through the three endpoints that share it: GET /api/time-entries,
| stats/overview, stats/pulse.
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

it('AC-016: a responsabile reads list/overview/pulse for a DIRECT and an INDIRECT subordinate, can_write false', function () {
    $manager = timeEntryActorWith(['viewAny']);
    $direct = User::factory()->create();
    $indirect = User::factory()->create();
    EmploymentProfile::factory()->manager()->create(['user_id' => $manager->id]);
    EmploymentProfile::factory()->reportsTo($manager)->create(['user_id' => $direct->id]);
    EmploymentProfile::factory()->reportsTo($direct)->create(['user_id' => $indirect->id]);
    Sanctum::actingAs($manager);

    foreach ([$direct, $indirect] as $target) {
        $list = $this->getJson("/api/time-entries?user_id={$target->id}&date_from=2026-09-14&date_to=2026-09-14")
            ->assertOk();
        expect($list->json('meta.can_write'))->toBeFalse()
            ->and($list->json('meta.selected_user.id'))->toBe($target->id);

        $this->getJson("/api/time-entries/stats/overview?user_id={$target->id}&date_from=2026-09-14&date_to=2026-09-14")->assertOk();
        $this->getJson("/api/time-entries/stats/pulse?user_id={$target->id}&date_from=2026-09-14&date_to=2026-09-14")->assertOk();
    }
});

it('AC-016: a responsabile requesting a STRANGER user_id is 403 on list/overview/pulse', function () {
    $manager = timeEntryActorWith(['viewAny']);
    EmploymentProfile::factory()->manager()->create(['user_id' => $manager->id]);
    $stranger = User::factory()->create();
    Sanctum::actingAs($manager);

    $this->getJson("/api/time-entries?user_id={$stranger->id}")->assertForbidden();
    $this->getJson("/api/time-entries/stats/overview?user_id={$stranger->id}")->assertForbidden();
    $this->getJson("/api/time-entries/stats/pulse?user_id={$stranger->id}")->assertForbidden();
});

it('a manageAll holder reads any user\'s list/overview/pulse regardless of the reporting tree', function () {
    $admin = timeEntryActorWith(['viewAny', 'manageAll']);
    $stranger = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->getJson("/api/time-entries?user_id={$stranger->id}")->assertOk();
    $this->getJson("/api/time-entries/stats/overview?user_id={$stranger->id}")->assertOk();
    $this->getJson("/api/time-entries/stats/pulse?user_id={$stranger->id}")->assertOk();
});

it('without time-entries.viewAny, list/overview/pulse are all 403', function () {
    Permission::findOrCreate('time-entries.viewAny');
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries')->assertForbidden();
    $this->getJson('/api/time-entries/stats/overview')->assertForbidden();
    $this->getJson('/api/time-entries/stats/pulse')->assertForbidden();
});
