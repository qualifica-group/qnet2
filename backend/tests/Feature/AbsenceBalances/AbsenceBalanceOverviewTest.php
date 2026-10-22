<?php

use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-19 10:00:00');
    $this->hr = grantAbsencePermissions(User::factory()->create(), ['absence-requests.manageAll']);
});

it('0304 AC-013: the overview is HR-only and validates the filters', function () {
    Sanctum::actingAs(grantAbsencePermissions(User::factory()->create(), ['absence-requests.viewAny']));
    $this->getJson('/api/absence-balances/overview')->assertForbidden();
    $this->getJson('/api/absence-balances/overview/export?format=csv')->assertForbidden();

    Sanctum::actingAs($this->hr);
    $this->getJson('/api/absence-balances/overview?per_page=101')->assertUnprocessable();
    $this->getJson('/api/absence-balances/overview?search='.str_repeat('a', 101))->assertUnprocessable();
    $this->getJson('/api/absence-balances/overview?business_function_id=999999')->assertUnprocessable();
    $this->getJson('/api/absence-balances/overview/export?format=pdf')->assertUnprocessable();
    $this->getJson('/api/absence-balances/overview/export')->assertUnprocessable();
});

it('0304 AC-013: lists active people with a profile by name, with types, balances and daily minutes', function () {
    $type = AbsenceType::factory()->create(['name' => 'Ferie', 'default_annual_days' => 26, 'color' => 'sky']);
    AbsenceType::factory()->notDeducting()->create();
    $bruno = balanceDailyPerson(240, 'Bruno');
    $anna = balanceDailyPerson(480, 'Anna');
    $inactive = balanceDailyPerson(480, 'Carlo');
    $inactive->update(['is_active' => false]);
    User::factory()->create(['name' => 'Senza profilo']);
    AbsenceAllowance::factory()->create(['user_id' => $anna->id, 'absence_type_id' => $type->id, 'year' => 2026, 'annual_minutes' => 9600, 'carried_over_minutes' => 60]);
    AbsenceRequest::factory()->forUser($anna)->approved(480)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-04']);
    AbsenceRequest::factory()->forUser($anna)->submitted(120)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']);
    Sanctum::actingAs($this->hr);

    $response = $this->getJson('/api/absence-balances/overview?year=2026&per_page=2')->assertOk();

    $response->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.types', [['id' => $type->id, 'name' => 'Ferie', 'color' => 'sky']])
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'total' => 2])
        ->assertJsonPath('data.people.0.user.name', 'Anna')
        ->assertJsonPath('data.people.0.daily_minutes', 480)
        ->assertJsonPath('data.people.0.balances.0.source', 'allowance')
        ->assertJsonPath('data.people.0.balances.0.annual_minutes', 9600)
        ->assertJsonPath('data.people.0.balances.0.carried_over_minutes', 60)
        ->assertJsonPath('data.people.0.balances.0.used_minutes', 480)
        ->assertJsonPath('data.people.0.balances.0.pending_minutes', 120)
        ->assertJsonPath('data.people.1.user.id', $bruno->id)
        ->assertJsonPath('data.people.1.daily_minutes', 240)
        ->assertJsonPath('data.people.1.balances.0.source', 'default')
        ->assertJsonPath('data.people.1.balances.0.annual_minutes', 6240);
});

it('0304 AC-013: filters by function, site and search, and paginates', function () {
    AbsenceType::factory()->create();
    $function = BusinessFunction::factory()->create();
    $site = OperationalSite::factory()->create();
    $inBoth = balanceDailyPerson(480, 'Anna Rossi');
    $inFunction = balanceDailyPerson(480, 'Bruno Verdi');
    $other = balanceDailyPerson(480, 'Carla Neri');
    $inBoth->businessFunctions()->attach($function->id);
    $inFunction->businessFunctions()->attach($function->id);
    $inBoth->employment->operationalSites()->attach($site->id, ['is_primary' => true]);
    Sanctum::actingAs($this->hr);

    $names = fn (string $query): array => collect($this->getJson("/api/absence-balances/overview?{$query}")->assertOk()->json('data.people'))->pluck('user.name')->all();

    expect($names("business_function_id={$function->id}"))->toBe(['Anna Rossi', 'Bruno Verdi'])
        ->and($names("operational_site_id={$site->id}"))->toBe(['Anna Rossi'])
        ->and($names('search=verdi'))->toBe(['Bruno Verdi'])
        ->and($names('search=%'))->toBe([]);

    $this->getJson('/api/absence-balances/overview?per_page=2&page=2')
        ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 2, 'total' => 3])
        ->assertJsonPath('data.people.0.user.id', $other->id);
});

it('0304 AC-013: the number of queries does not grow with the people', function () {
    $type = AbsenceType::factory()->create(['default_annual_days' => 26]);
    AbsenceType::factory()->create(['default_annual_days' => 5]);
    Sanctum::actingAs($this->hr);
    $countQueries = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/absence-balances/overview?year=2026')->assertOk();
        $this->getJson('/api/absence-balances/overview/export?format=csv')->assertOk();

        return count(DB::getQueryLog());
    };

    balanceDailyPerson(480);
    $countQueries();
    $few = $countQueries();

    foreach (range(1, 6) as $i) {
        $person = balanceDailyPerson(480);
        AbsenceAllowance::factory()->create(['user_id' => $person->id, 'absence_type_id' => $type->id, 'year' => 2026]);
        AbsenceRequest::factory()->forUser($person)->approved(480)->create(['absence_type_id' => $type->id, 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-04']);
    }

    expect($countQueries())->toBe($few);
});

it('0304 AC-013: exports one row per person and type as csv and xlsx', function () {
    $ferie = AbsenceType::factory()->create(['name' => 'Ferie', 'sort_order' => 1]);
    AbsenceType::factory()->create(['name' => 'ROL', 'sort_order' => 2, 'default_annual_days' => null]);
    $anna = balanceDailyPerson(480, 'Anna');
    balanceDailyPerson(240, 'Bruno');
    AbsenceAllowance::factory()->create(['user_id' => $anna->id, 'absence_type_id' => $ferie->id, 'year' => 2026, 'annual_minutes' => 9600]);
    AbsenceRequest::factory()->forUser($anna)->approved(480)->create(['absence_type_id' => $ferie->id, 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-04']);
    Sanctum::actingAs($this->hr);

    $csv = $this->get('/api/absence-balances/overview/export?format=csv&year=2026');
    $csv->assertOk();
    $lines = array_values(array_filter(preg_split('/\R/', ltrim(file_get_contents($csv->getFile()->getPathname()), "\xEF\xBB\xBF"))));

    expect($lines)->toHaveCount(5)
        ->and($lines[0])->toStartWith('Person,"Absence type"')
        ->and($lines[1])->toStartWith('Anna,Ferie,160,20,0,0,')
        ->and($lines[1])->toContain(',8,1,');

    $xlsx = $this->get('/api/absence-balances/overview/export?format=xlsx&year=2026');
    $xlsx->assertOk();
    expect($xlsx->headers->get('content-disposition'))->toContain('absence-balances-2026.xlsx')
        ->and(filesize($xlsx->getFile()->getPathname()))->toBeGreaterThan(0);
});
