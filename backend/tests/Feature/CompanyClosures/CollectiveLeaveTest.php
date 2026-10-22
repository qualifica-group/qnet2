<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\CompanyClosure;
use App\Models\OperationalSite;
use App\Models\User;
use App\Services\Absences\Balances\AbsenceBalanceBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

/** @param array<string, mixed> $profile */
function leavePerson(array $profile = [], ?OperationalSite $site = null): User
{
    $person = User::factory()->create();
    $employment = absenceProfile($person, null, $profile);

    if ($site !== null) {
        $employment->operationalSites()->attach($site->id, ['is_primary' => true]);
    }

    return $person;
}

/** @param array<string, mixed> $overrides */
function leavePayload(AbsenceType $type, array $overrides = []): array
{
    return [
        'name' => 'August break',
        'starts_on' => '2026-12-14',
        'ends_on' => '2026-12-18',
        'kind' => 'collective_leave',
        'absence_type_id' => $type->id,
        'applies_to_all_sites' => true,
        ...$overrides,
    ];
}

function leaveAuthor(): User
{
    return Sanctum::actingAs(hrCatalogUserWith('company-closures', ['create', 'update', 'delete', 'view']));
}

it('0304 AC-016: creation generates approved requests for the active people with a profile, with their own hours', function () {
    $author = leaveAuthor();
    $type = AbsenceType::factory()->create();
    $full = leavePerson();
    $partTime = leavePerson(['standard_daily_minutes' => 240]);
    $inactive = leavePerson();
    $inactive->update(['is_active' => false]);
    User::factory()->create();
    $before = DB::table('notifications')->count();

    $response = $this->postJson('/api/company-closures', leavePayload($type))
        ->assertCreated()
        ->assertJsonPath('data.generation.created', 2)
        ->assertJsonPath('data.generation.skipped', [])
        ->assertJsonPath('data.generated_requests_count', 2);
    $closureId = $response->json('data.id');

    $requests = AbsenceRequest::query()->where('company_closure_id', $closureId)->with('approvers')->get()->keyBy('user_id');

    expect($requests->keys()->sort()->values()->all())->toBe(collect([$full->id, $partTime->id])->sort()->values()->all())
        ->and($requests[$full->id]->minutes)->toBe(5 * 480)
        ->and($requests[$partTime->id]->minutes)->toBe(5 * 240)
        ->and($requests[$full->id]->status)->toBe(AbsenceRequestStatus::Approved)
        ->and($requests[$full->id]->is_full_day)->toBeTrue()
        ->and($requests[$full->id]->is_short_notice)->toBeFalse()
        ->and($requests[$full->id]->created_by)->toBe($author->id)
        ->and($requests[$full->id]->decided_by)->toBe($author->id)
        ->and($requests[$full->id]->decided_at)->not->toBeNull()
        ->and($requests[$full->id]->approvers)->toBeEmpty()
        ->and($requests[$full->id]->absence_type_id)->toBe($type->id)
        ->and(DB::table('notifications')->count())->toBe($before);
});

it('0304 AC-016: the generated days leave out holidays and the balances of the people go down', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create(['deducts_balance' => true]);
    $person = leavePerson();
    $builder = app(AbsenceBalanceBuilder::class);
    $usedBefore = $builder->row($person, $type, 2026)['used_minutes'];

    // 2026-12-08 is a national holiday: 4 working days in Mon 7 - Fri 11
    $this->postJson('/api/company-closures', leavePayload($type, ['starts_on' => '2026-12-07', 'ends_on' => '2026-12-11']))->assertCreated();

    expect($builder->row($person, $type, 2026)['used_minutes'])->toBe($usedBefore + 4 * 480);
});

it('0304 AC-016: who has an overlapping absence is skipped and reported, as is who has no working day', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    $busy = leavePerson();
    $free = leavePerson();
    AbsenceRequest::factory()->forUser($busy)->approved()->create(['starts_on' => '2026-12-16', 'ends_on' => '2026-12-16']);
    AbsenceRequest::factory()->forUser($free)->create(['starts_on' => '2026-12-16', 'ends_on' => '2026-12-16']);

    $this->postJson('/api/company-closures', leavePayload($type))
        ->assertCreated()
        ->assertJsonPath('data.generation.created', 1)
        ->assertJsonPath('data.generation.skipped', [['user' => ['id' => $busy->id, 'name' => $busy->name], 'reason' => 'overlap']]);

    $weekend = $this->postJson('/api/company-closures', leavePayload($type, ['name' => 'Weekend', 'starts_on' => '2026-12-12', 'ends_on' => '2026-12-13']))
        ->assertCreated()->assertJsonPath('data.generation.created', 0)->json('data.generation.skipped');

    expect(collect($weekend)->pluck('reason')->unique()->all())->toBe(['no_working_day'])
        ->and($weekend)->toHaveCount(2);
});

it('0304 AC-016: a closure of one site only reaches the people of that site', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    $siteA = OperationalSite::factory()->create();
    $siteB = OperationalSite::factory()->create();
    $inA = leavePerson([], $siteA);
    leavePerson([], $siteB);
    leavePerson();

    $this->postJson('/api/company-closures', leavePayload($type, ['applies_to_all_sites' => false, 'operational_site_ids' => [$siteA->id]]))
        ->assertCreated()->assertJsonPath('data.generation.created', 1);

    expect(AbsenceRequest::query()->whereNotNull('company_closure_id')->pluck('user_id')->all())->toBe([$inA->id]);
});

it('0304 AC-016: an update before the start regenerates without duplicates, a change to holiday removes them', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    leavePerson();
    leavePerson();
    $id = $this->postJson('/api/company-closures', leavePayload($type))->assertCreated()->json('data.id');

    $this->putJson("/api/company-closures/{$id}", leavePayload($type, ['starts_on' => '2026-12-21', 'ends_on' => '2026-12-22']))
        ->assertOk()->assertJsonPath('data.generation.created', 2)->assertJsonPath('data.generated_requests_count', 2);

    $rows = AbsenceRequest::query()->where('company_closure_id', $id)->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('starts_on')->map->format('Y-m-d')->unique()->all())->toBe(['2026-12-21']);

    $this->putJson("/api/company-closures/{$id}", ['name' => 'Now a holiday', 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-22', 'kind' => 'holiday', 'applies_to_all_sites' => true])
        ->assertOk()->assertJsonMissingPath('data.generation')->assertJsonPath('data.absence_type', null)->assertJsonPath('data.generated_requests_count', 0);
    expect(AbsenceRequest::query()->where('company_closure_id', $id)->count())->toBe(0);

    $this->putJson("/api/company-closures/{$id}", leavePayload($type, ['starts_on' => '2026-12-21', 'ends_on' => '2026-12-22']))
        ->assertOk()->assertJsonPath('data.generation.created', 2);
});

it('0304 AC-016: deleting a closure before its start removes the generated requests', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    leavePerson();
    $id = $this->postJson('/api/company-closures', leavePayload($type))->assertCreated()->json('data.id');

    $this->deleteJson("/api/company-closures/{$id}")->assertNoContent();

    expect(CompanyClosure::find($id))->toBeNull()
        ->and(AbsenceRequest::query()->whereNotNull('company_closure_id')->count())->toBe(0);
});

it('0304 AC-016: a started collective leave is frozen and keeps its requests', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    $person = leavePerson();
    $closure = CompanyClosure::factory()->collectiveLeave($type)->create(['starts_on' => '2026-10-22', 'ends_on' => '2026-10-23']);
    AbsenceRequest::factory()->forUser($person)->approved()->create(['company_closure_id' => $closure->id, 'absence_type_id' => $type->id, 'starts_on' => '2026-10-22', 'ends_on' => '2026-10-23']);

    $this->putJson("/api/company-closures/{$closure->id}", leavePayload($type))->assertStatus(409);
    $this->deleteJson("/api/company-closures/{$closure->id}")->assertStatus(409);

    expect(AbsenceRequest::query()->where('company_closure_id', $closure->id)->count())->toBe(1);
});

it('0304 AC-016: the person cannot cancel the generated request, the HR office can', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    $person = leavePerson();
    $this->postJson('/api/company-closures', leavePayload($type))->assertCreated();
    $request = AbsenceRequest::query()->where('user_id', $person->id)->firstOrFail();

    Sanctum::actingAs(grantAbsencePermissions($person, ['absence-requests.viewAny', 'absence-requests.create']));
    $this->getJson("/api/absence-requests/{$request->id}")
        ->assertOk()->assertJsonPath('data.can.cancel', false)->assertJsonPath('data.company_closure.name', 'August break');
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertForbidden();

    Sanctum::actingAs(absenceUserWith(['viewAny', 'create', 'manageAll']));
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('0304 AC-016: the generation is atomic: a failing write leaves nothing behind', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    leavePerson();

    $this->postJson('/api/company-closures', leavePayload($type, ['absence_type_id' => 999999]))->assertStatus(422);

    expect(CompanyClosure::count())->toBe(0)->and(AbsenceRequest::query()->whereNotNull('company_closure_id')->count())->toBe(0);
});

it('0304 AC-016: generating for more people does not add queries per person', function () {
    leaveAuthor();
    $type = AbsenceType::factory()->create();
    leavePerson();

    // distinct weeks, so no closure finds the people busy with the previous one
    $queries = function (string $name, string $day) use ($type): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->postJson('/api/company-closures', leavePayload($type, ['name' => $name, 'starts_on' => $day, 'ends_on' => $day]))->assertCreated();

        return count(DB::getQueryLog());
    };

    $queries('warm-up', '2026-12-14');
    $few = $queries('few', '2026-12-15');
    foreach (range(1, 6) as $ignored) {
        leavePerson();
    }

    expect($queries('many', '2026-12-16'))->toBe($few);
});
