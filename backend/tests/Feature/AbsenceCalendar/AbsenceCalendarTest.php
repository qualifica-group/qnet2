<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0279 — absence calendar: three permission levels, approved and pending
// requests split by working day, detail openable only where 0278 allows it.
uses(RefreshDatabase::class);

const CALENDAR_URL = '/api/absences/calendar?from=2026-12-01&to=2026-12-31';

function calendarActor(string ...$levels): User
{
    return grantAbsencePermissions(User::factory()->create(), array_map(
        static fn (string $level): string => "absence-requests.calendar{$level}",
        $levels,
    ));
}

function calendarAbsence(User $person, string $state, array $attributes = []): AbsenceRequest
{
    return AbsenceRequest::factory()->forUser($person)->{$state}()->create([
        'absence_type_id' => (AbsenceType::query()->firstWhere('name', 'Ferie') ?? AbsenceType::factory()->create(['name' => 'Ferie']))->id,
        'starts_on' => '2026-12-14',
        'ends_on' => '2026-12-14',
        ...$attributes,
    ]);
}

/** @return list<int> */
function calendarPeopleIds(mixed $response): array
{
    return collect($response->json('data.people'))->pluck('id')->all();
}

it('AC-001: calendarOwn shows only the actor', function () {
    $actor = calendarActor('Own');
    $subordinate = User::factory()->create();
    absenceProfile($subordinate, $actor);
    Sanctum::actingAs($actor);

    $response = $this->getJson(CALENDAR_URL)->assertOk()->assertJsonPath('data.scope', 'own');

    expect(calendarPeopleIds($response))->toBe([$actor->id]);
});

it('AC-001: calendarTeam shows the actor and their active descendants', function () {
    $actor = calendarActor('Team');
    $direct = User::factory()->create(['name' => 'Anna']);
    $indirect = User::factory()->create(['name' => 'Bruno']);
    $inactive = User::factory()->create(['is_active' => false]);
    $stranger = User::factory()->create();
    absenceProfile($direct, $actor);
    absenceProfile($indirect, $direct);
    absenceProfile($inactive, $actor);
    Sanctum::actingAs($actor);

    $ids = calendarPeopleIds($this->getJson(CALENDAR_URL)->assertOk()->assertJsonPath('data.scope', 'team'));

    expect($ids)->toContain($actor->id, $direct->id, $indirect->id)
        ->not->toContain($inactive->id, $stranger->id);
});

it('AC-001: calendarAll shows every active person and the widest permission wins', function () {
    $actor = calendarActor('Own', 'All');
    $colleague = User::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);
    Sanctum::actingAs($actor);

    $ids = calendarPeopleIds($this->getJson(CALENDAR_URL)->assertOk()->assertJsonPath('data.scope', 'all'));

    expect($ids)->toContain($actor->id, $colleague->id)->not->toContain($inactive->id);
});

it('AC-001: without any calendar permission the calendar is 403', function () {
    Sanctum::actingAs(absenceUserWith(['viewAny', 'create', 'manageAll']));

    $this->getJson(CALENDAR_URL)->assertForbidden();
});

it('AC-002: only approved and submitted requests, split by working day, people without absences included', function () {
    $actor = calendarActor('All');
    $person = User::factory()->create();
    // Mon 7 to Wed 9 December: the 8th is a holiday, so two entries.
    calendarAbsence($person, 'approved', ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-09']);
    calendarAbsence($person, 'submitted', ['is_full_day' => false, 'minutes' => 120, 'starts_on' => '2026-12-15', 'ends_on' => '2026-12-15']);
    foreach (['draft', 'rejected', 'cancelled'] as $status) {
        AbsenceRequest::factory()->forUser($person)->create(['status' => $status, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-21']);
    }
    // Touches the period only on its last day.
    calendarAbsence($person, 'approved', ['starts_on' => '2026-11-27', 'ends_on' => '2026-12-01']);
    Sanctum::actingAs($actor);

    $response = $this->getJson(CALENDAR_URL)->assertOk();
    $people = collect($response->json('data.people'))->keyBy('id');
    $absences = collect($people[$person->id]['absences']);

    expect($absences->pluck('date')->all())->toBe(['2026-12-01', '2026-12-07', '2026-12-09', '2026-12-15'])
        ->and($absences->firstWhere('date', '2026-12-15'))->toMatchArray([
            'status' => 'submitted', 'absence_type_name' => 'Ferie', 'is_full_day' => false, 'minutes' => 120,
        ])
        ->and($absences->firstWhere('date', '2026-12-07'))->toMatchArray(['status' => 'approved', 'is_full_day' => true, 'minutes' => null])
        ->and($people[$actor->id]['absences'])->toBe([])
        ->and($response->json('data.days'))->toHaveCount(31)
        ->and(collect($response->json('data.days'))->firstWhere('date', '2026-12-08'))->toBe(['date' => '2026-12-08', 'is_non_working_day' => true, 'is_holiday' => true])
        ->and(collect($response->json('data.days'))->firstWhere('date', '2026-12-05'))->toBe(['date' => '2026-12-05', 'is_non_working_day' => true, 'is_holiday' => false]);
});

it('AC-003: can_open follows the visibility of spec 0278', function () {
    $actor = calendarActor('All');
    $subordinate = User::factory()->create();
    absenceProfile($subordinate, $actor);
    $stranger = User::factory()->create();
    $approvedByActor = User::factory()->create();
    calendarAbsence($actor, 'approved');
    calendarAbsence($subordinate, 'approved');
    calendarAbsence($stranger, 'approved');
    calendarAbsence($approvedByActor, 'submitted')->approvers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $openable = collect($this->getJson(CALENDAR_URL)->assertOk()->json('data.people'))
        ->mapWithKeys(fn (array $person): array => [$person['id'] => $person['absences'][0]['can_open'] ?? null]);

    expect($openable[$actor->id])->toBeTrue()
        ->and($openable[$subordinate->id])->toBeTrue()
        ->and($openable[$approvedByActor->id])->toBeTrue()
        ->and($openable[$stranger->id])->toBeFalse();
});

it('AC-003: the HR office opens every request', function () {
    $actor = grantAbsencePermissions(calendarActor('All'), ['absence-requests.manageAll']);
    $stranger = User::factory()->create();
    calendarAbsence($stranger, 'approved');
    Sanctum::actingAs($actor);

    $people = collect($this->getJson(CALENDAR_URL)->assertOk()->json('data.people'))->keyBy('id');

    expect($people[$stranger->id]['absences'][0]['can_open'])->toBeTrue();
});

it('AC-004: the function filter keeps only its members', function () {
    $actor = calendarActor('All');
    $function = BusinessFunction::factory()->create();
    $member = User::factory()->create();
    $member->businessFunctions()->attach($function->id);
    $outsider = User::factory()->create();
    Sanctum::actingAs($actor);

    $ids = calendarPeopleIds($this->getJson(CALENDAR_URL."&business_function_id={$function->id}")->assertOk());

    expect($ids)->toBe([$member->id])->not->toContain($outsider->id);
});

it('AC-004: an inverted, too long or unknown-function request is 422', function (string $query, string $field) {
    Sanctum::actingAs(calendarActor('Own'));

    $this->getJson("/api/absences/calendar?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'inverted' => ['from=2026-12-10&to=2026-12-01', 'to'],
    '63 days' => ['from=2026-01-01&to=2026-03-04', 'to'],
    'missing from' => ['to=2026-12-01', 'from'],
    'unknown function' => ['from=2026-12-01&to=2026-12-31&business_function_id=999999', 'business_function_id'],
]);

it('AC-004: 62 days are accepted', function () {
    Sanctum::actingAs(calendarActor('Own'));

    $this->getJson('/api/absences/calendar?from=2026-01-01&to=2026-03-03')->assertOk()->assertJsonCount(62, 'data.days');
});

it('AC-004: the query count does not grow with the people', function () {
    $actor = calendarActor('All');
    Sanctum::actingAs($actor);
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(CALENDAR_URL)->assertOk();

        return count(DB::getQueryLog());
    };

    calendarAbsence(User::factory()->create(), 'approved');
    // The first request also warms the permission cache.
    $countQueries();
    $few = $countQueries();

    foreach (range(1, 5) as $ignored) {
        calendarAbsence(User::factory()->create(), 'submitted')->approvers()->attach($actor->id);
    }

    expect($countQueries())->toBe($few);
});

it('AC-005: permissions:sync creates the three calendar permissions, offered under Assenze', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $calendar = ['absence-requests.calendarAll', 'absence-requests.calendarOwn', 'absence-requests.calendarTeam'];

    expect(Permission::query()->whereIn('name', $calendar)->pluck('name')->sort()->values()->all())->toBe($calendar)
        ->and(app(AssignablePermissionCatalogue::class)->names())->toContain(...$calendar);

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('roles.viewAny'));
    $resource = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'))
        ->flatMap(fn (array $area): array => $area['resources'])
        ->firstWhere('resource', 'absence-requests');

    expect(collect($resource['permissions'])->pluck('name')->all())->toContain(...$calendar)
        ->and($resource['label_key'])->toBe('navigation.absences');
});
