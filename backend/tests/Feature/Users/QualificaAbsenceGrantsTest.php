<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OwnAbsencesCatalogue;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0292: the Assenze are every mansione's, like the Segnatempo — one's own
// and, for a manager, the team's; everyone's only through a permission.
uses(RefreshDatabase::class);

const OWN_ABSENCES_CALENDAR_URL = '/api/absences/calendar?from=2026-12-01&to=2026-12-31';

const EVERYONE_ABSENCE_PERMISSIONS = ['absence-requests.manageAll', 'absence-requests.calendarAll'];

it('AC-001: opens own and team absences to every role, never everyone\'s', function () {
    $this->seed(QualificaOperatorSeeder::class);
    $this->seed(QualificaStaffSeeder::class);

    foreach (array_keys(OperatorRoleCatalogue::ROLES) as $name) {
        $user = User::role($name)->firstOrFail();
        $routes = visibleRoutes($user);

        foreach (OwnAbsencesCatalogue::PERMISSIONS as $permission) {
            expect($user->can($permission))->toBeTrue("{$name} {$permission}");
        }

        foreach (EVERYONE_ABSENCE_PERMISSIONS as $permission) {
            expect($user->can($permission))->toBeFalse("{$name} {$permission}");
        }

        expect(array_search('/absences', $routes, true))->toBe(array_search('/time-entries', $routes, true) + 1);
    }
});

it('AC-003: a base-role manager sees their team\'s absences, a person without a team only their own', function () {
    $this->seed(QualificaRoleSeeder::class);

    [$manager, $subordinate, $stranger] = collect(range(1, 3))
        ->map(fn (): User => User::factory()->create()->assignRole(OperatorRoleCatalogue::BASE_ROLE))
        ->all();
    absenceProfile($subordinate, $manager);
    $hrOffice = User::factory()->create();
    $teamRequest = absenceSubmitted($subordinate, $hrOffice);
    $strangerRequest = absenceSubmitted($stranger, $hrOffice);

    Sanctum::actingAs($manager);

    $people = $this->getJson(OWN_ABSENCES_CALENDAR_URL)->assertOk()
        ->assertJsonPath('data.scope', 'team')
        ->json('data.people');
    $rows = $this->postJson('/api/tables/absence-requests-all/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()
        ->json('items');

    expect(collect($people)->pluck('id')->all())->toContain($manager->id, $subordinate->id)->not->toContain($stranger->id)
        ->and(collect($rows)->pluck('id')->all())->toContain($teamRequest->id)->not->toContain($strangerRequest->id);

    $this->getJson("/api/absence-requests/{$strangerRequest->id}")->assertNotFound();

    Sanctum::actingAs($subordinate);

    $ownPeople = $this->getJson(OWN_ABSENCES_CALENDAR_URL)->assertOk()->json('data.people');

    expect(collect($ownPeople)->pluck('id')->all())->toBe([$subordinate->id]);
});

it('AC-004: the HR office keeps everyone\'s absences', function () {
    $this->seed(QualificaRoleSeeder::class);

    $hrOffice = Role::findByName(HrOfficeRoleCatalogue::ROLE);

    foreach (EVERYONE_ABSENCE_PERMISSIONS as $permission) {
        expect($hrOffice->hasPermissionTo($permission))->toBeTrue($permission);
    }
});
