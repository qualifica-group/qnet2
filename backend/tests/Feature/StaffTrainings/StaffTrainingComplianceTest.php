<?php

use App\Models\BusinessFunction;
use App\Models\StaffCourse;
use App\Models\StaffTraining;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 19)->startOfDay());
    config(['staff_training.expiring_days' => 60]);
});

function trainingRows(array $query = []): array
{
    return test()->getJson('/api/staff-trainings/compliance?'.http_build_query($query))->assertOk()->json('data.rows');
}

function trainingStatuses(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $row) => ["{$row['user']['name']}|{$row['course']['name']}" => $row['status']])->all();
}

it('0284 AC-004: states follow the latest passed training, missing covers the required courses', function () {
    $course = fn (string $name, ?int $months = 12) => StaffCourse::factory()->create(['name' => $name, 'validity_months' => $months]);
    [$valid, $expiring, $expired, $missing, $never] = [$course('A valid'), $course('B expiring'), $course('C expired'), $course('D missing'), $course('E never', null)];
    $person = User::factory()->create(['name' => 'Anna']);
    trainingProfile($person, null, [trainingRequiredBy([$valid, $expiring, $expired, $missing, $never])]);

    $make = fn (StaffCourse $c, string $expires, array $extra = []) => StaffTraining::factory()->forUser($person)->create([
        'staff_course_id' => $c->id, 'completed_on' => '2026-01-01', 'expires_on' => $expires, ...$extra,
    ]);
    $make($valid, '2027-01-01');
    $make($expiring, '2026-12-18');
    $make($expired, '2026-10-18');
    StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $never->id, 'completed_on' => '2026-01-01', 'expires_on' => null]);

    Sanctum::actingAs(trainingUserWith(['viewAll']));
    $body = $this->getJson('/api/staff-trainings/compliance')->assertOk()->assertJsonPath('data.expiring_days', 60)->json('data');

    expect(trainingStatuses($body['rows']))->toBe([
        'Anna|A valid' => 'valid',
        'Anna|B expiring' => 'expiring',
        'Anna|C expired' => 'expired',
        'Anna|D missing' => 'missing',
        'Anna|E never' => 'valid',
    ])->and($body['rows'][0])->toHaveKeys(['user', 'course', 'required', 'status', 'completed_on', 'expires_on', 'training_id'])
        ->and($body['rows'][3]['training_id'])->toBeNull()
        ->and($body['rows'][3]['completed_on'])->toBeNull()
        ->and($body['rows'][3]['required'])->toBeTrue();
});

it('0284 AC-004: the expiring window includes the boundary day, expired is strictly before today', function () {
    $course = StaffCourse::factory()->create();
    $edge = User::factory()->create(['name' => 'Edge']);
    $today = User::factory()->create(['name' => 'Today']);
    $late = User::factory()->create(['name' => 'Late']);
    foreach ([[$edge, '2026-12-18'], [$today, '2026-10-19'], [$late, '2026-12-19']] as [$person, $expires]) {
        StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $course->id, 'expires_on' => $expires]);
    }

    Sanctum::actingAs(trainingUserWith(['viewAll']));

    expect(trainingStatuses(trainingRows()))->toBe(['Edge|'.$course->name => 'expiring', 'Late|'.$course->name => 'valid', 'Today|'.$course->name => 'expiring']);
});

it('0284 AC-004: the most recent passed training decides, a later failed one does not', function () {
    $course = StaffCourse::factory()->create();
    $person = User::factory()->create(['name' => 'Anna']);
    trainingProfile($person, null, [trainingRequiredBy([$course])]);
    StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $course->id, 'completed_on' => '2024-01-01', 'expires_on' => '2025-01-01']);
    $latest = StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $course->id, 'completed_on' => '2026-05-01', 'expires_on' => '2027-05-01']);
    StaffTraining::factory()->forUser($person)->failed()->create(['staff_course_id' => $course->id, 'completed_on' => '2026-09-01', 'expires_on' => null]);

    Sanctum::actingAs(trainingUserWith(['viewAll']));
    $rows = trainingRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('valid')
        ->and($rows[0]['training_id'])->toBe($latest->id);
});

it('0284 AC-004: a required course with only failed trainings is missing; an unrequired one with only failed is absent', function () {
    $required = StaffCourse::factory()->create(['name' => 'Required']);
    $optional = StaffCourse::factory()->create(['name' => 'Optional']);
    $person = User::factory()->create(['name' => 'Anna']);
    trainingProfile($person, null, [trainingRequiredBy([$required])]);
    StaffTraining::factory()->forUser($person)->failed()->create(['staff_course_id' => $required->id]);
    StaffTraining::factory()->forUser($person)->failed()->create(['staff_course_id' => $optional->id]);

    Sanctum::actingAs(trainingUserWith(['viewAll']));

    expect(trainingStatuses(trainingRows()))->toBe(['Anna|Required' => 'missing']);
});

it('0284 AC-004: an unrequired course shows when passed; an inactive required one is not missing', function () {
    $optional = StaffCourse::factory()->create(['name' => 'Optional']);
    $retired = StaffCourse::factory()->inactive()->create(['name' => 'Retired']);
    $person = User::factory()->create(['name' => 'Anna']);
    trainingProfile($person, null, [trainingRequiredBy([$retired])]);
    StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $optional->id, 'expires_on' => null]);

    Sanctum::actingAs(trainingUserWith(['viewAll']));
    $rows = trainingRows();

    expect(trainingStatuses($rows))->toBe(['Anna|Optional' => 'valid'])
        ->and($rows[0]['required'])->toBeFalse();
});

it('0284 AC-004: a course required through several roles appears once; inactive people are skipped; order is person then course', function () {
    $b = StaffCourse::factory()->create(['name' => 'B']);
    $a = StaffCourse::factory()->create(['name' => 'A']);
    $zoe = User::factory()->create(['name' => 'Zoe']);
    $anna = User::factory()->create(['name' => 'Anna']);
    $gone = User::factory()->create(['name' => 'Gone', 'is_active' => false]);
    trainingProfile($zoe, null, [trainingRequiredBy([$a, $b]), trainingRequiredBy([$a])]);
    trainingProfile($anna, null, [trainingRequiredBy([$b])]);
    trainingProfile($gone, null, [trainingRequiredBy([$a])]);

    Sanctum::actingAs(trainingUserWith(['viewAll']));

    expect(array_keys(trainingStatuses(trainingRows())))->toBe(['Anna|B', 'Zoe|A', 'Zoe|B']);
});

it('0284 AC-004: filters narrow by status, course and business function', function () {
    $a = StaffCourse::factory()->create(['name' => 'A']);
    $b = StaffCourse::factory()->create(['name' => 'B']);
    $anna = User::factory()->create(['name' => 'Anna']);
    $bruno = User::factory()->create(['name' => 'Bruno']);
    $function = BusinessFunction::factory()->create();
    $bruno->businessFunctions()->attach($function->id);
    trainingProfile($anna, null, [trainingRequiredBy([$a, $b])]);
    trainingProfile($bruno, null, [trainingRequiredBy([$a, $b])]);
    StaffTraining::factory()->forUser($anna)->create(['staff_course_id' => $a->id, 'expires_on' => '2027-06-01']);

    Sanctum::actingAs(trainingUserWith(['viewAll']));

    expect(array_keys(trainingStatuses(trainingRows(['status' => 'valid']))))->toBe(['Anna|A'])
        ->and(array_keys(trainingStatuses(trainingRows(['status' => 'missing']))))->toBe(['Anna|B', 'Bruno|A', 'Bruno|B'])
        ->and(array_keys(trainingStatuses(trainingRows(['staff_course_id' => $b->id]))))->toBe(['Anna|B', 'Bruno|B'])
        ->and(array_keys(trainingStatuses(trainingRows(['business_function_id' => $function->id]))))->toBe(['Bruno|A', 'Bruno|B']);

    $this->getJson('/api/staff-trainings/compliance?status=bogus')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('0284 AC-004: the people follow the visibility of the actor', function () {
    $course = StaffCourse::factory()->create(['name' => 'C']);
    $manager = trainingUserWith(['viewTeam'], ['name' => 'Manager']);
    $report = User::factory()->create(['name' => 'Report']);
    $stranger = User::factory()->create(['name' => 'Stranger']);
    $role = trainingRequiredBy([$course]);
    foreach ([$manager, $report, $stranger] as $person) {
        trainingProfile($person, $person->is($report) ? $manager : null, [$role]);
    }

    Sanctum::actingAs($manager);
    expect(array_keys(trainingStatuses(trainingRows())))->toBe(['Manager|C', 'Report|C']);

    $own = trainingUserWith(['viewAny'], ['name' => 'Own']);
    trainingProfile($own, null, [$role]);
    Sanctum::actingAs($own);
    expect(array_keys(trainingStatuses(trainingRows())))->toBe(['Own|C']);
});

it('0284 AC-004: the query count does not grow with the number of people', function () {
    $courses = StaffCourse::factory()->count(3)->validFor(12)->create()->all();
    $role = trainingRequiredBy($courses);
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        trainingRows();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $enroll = function (int $people) use ($courses, $role): void {
        User::factory()->count($people)->create()->each(function (User $person) use ($courses, $role): void {
            trainingProfile($person, null, [$role]);
            StaffTraining::factory()->forUser($person)->create(['staff_course_id' => $courses[0]->id, 'expires_on' => '2027-06-01']);
        });
    };

    Sanctum::actingAs(trainingUserWith(['viewAll']));
    $enroll(2);
    trainingRows(); // warm-up: permission and config caches fill on the first request
    $few = $countQueries();
    $enroll(15);

    expect($countQueries())->toBe($few);
});
