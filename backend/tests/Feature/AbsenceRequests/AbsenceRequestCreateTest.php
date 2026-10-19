<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-19 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('AC-002: a Mon-Fri draft with a holiday has no minutes, the submission freezes 4 days x daily hours', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    Sanctum::actingAs($person);

    // 2026-12-08 (Tuesday) is a holiday
    $payload = absencePayload(['starts_on' => '2026-12-07', 'ends_on' => '2026-12-11']);
    $id = $this->postJson('/api/absence-requests', $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.minutes', null)
        ->assertJsonPath('data.approvers', [])
        ->json('data.id');

    $this->postJson("/api/absence-requests/{$id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.minutes', 4 * 480)
        ->assertJsonPath('data.approvers.0.id', $manager->id);
});

it('AC-002: submit=true on create files the request in one call and the response has the contract shape', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['submit' => true, 'note' => 'Holiday']))
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.minutes', 480)
        ->assertJsonPath('data.can.cancel', true)
        ->assertJsonPath('data.can.approve', false)
        ->assertJsonPath('data.attachment', null)
        ->assertJsonStructure(['success', 'message', 'data' => [
            'id', 'user' => ['id', 'name'], 'absence_type' => ['id', 'name', 'deducts_balance', 'requires_approval'],
            'status', 'is_full_day', 'starts_on', 'ends_on', 'minutes', 'requested_minutes', 'note', 'rejection_reason',
            'approvers', 'decided_by', 'decided_at', 'submitted_at', 'cancelled_at', 'attachment',
            'can' => ['update', 'delete', 'submit', 'approve', 'reject', 'cancel', 'attach'], 'created_at', 'updated_at',
        ]]);
});

it('AC-003: hourly minutes above the daily hours, or on a non working day, or a weekend-only range, are 422', function () {
    $person = absenceUserWith();
    absenceProfile($person, absenceUserWith());
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => false, 'requested_minutes' => 481]))
        ->assertUnprocessable()->assertJsonValidationErrors('requested_minutes');

    // Saturday
    $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => false, 'starts_on' => '2026-12-12', 'ends_on' => '2026-12-12', 'requested_minutes' => 60]))
        ->assertUnprocessable()->assertJsonValidationErrors('starts_on');

    // Saturday-Sunday
    $this->postJson('/api/absence-requests', absencePayload(['starts_on' => '2026-12-12', 'ends_on' => '2026-12-13']))
        ->assertUnprocessable()->assertJsonValidationErrors('starts_on');

    $this->postJson('/api/absence-requests', absencePayload(['is_full_day' => false, 'requested_minutes' => 120]))
        ->assertCreated()
        ->assertJsonPath('data.requested_minutes', 120);
});

it('AC-004: an overlap with a submitted or approved request is 422, with a rejected or cancelled one is allowed', function (string $state, bool $allowed) {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $existing = absenceSubmitted($person, $manager, ['status' => $state]);
    Sanctum::actingAs($person);

    $response = $this->postJson('/api/absence-requests', absencePayload(['starts_on' => '2026-12-11', 'ends_on' => '2026-12-14']));

    expect($existing->fresh()->status->value)->toBe($state);
    $allowed ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('starts_on');
})->with([
    'submitted' => ['submitted', false],
    'approved' => ['approved', false],
    'rejected' => ['rejected', true],
    'cancelled' => ['cancelled', true],
]);

it('AC-004: an overlap is also refused on update and on submit', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    absenceSubmitted($person, $manager, ['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);
    $draft = AbsenceRequest::factory()->forUser($person)->create(['absence_type_id' => AbsenceType::factory()->create()->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-21']);
    Sanctum::actingAs($person);

    $this->patchJson("/api/absence-requests/{$draft->id}", absencePayload(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']))
        ->assertUnprocessable()->assertJsonValidationErrors('starts_on');

    $draft->update(['starts_on' => '2026-12-14', 'ends_on' => '2026-12-14']);
    $this->postJson("/api/absence-requests/{$draft->id}/submit")
        ->assertUnprocessable()->assertJsonValidationErrors('starts_on');
});

it('AC-005: approvers are the active superiors, never the person, and inactive ones are skipped', function () {
    $active = absenceUserWith();
    $inactive = User::factory()->create(['is_active' => false]);
    $person = absenceUserWith();
    $profile = absenceProfile($person, $active);
    $profile->reportsTo()->attach($inactive->id);
    Sanctum::actingAs($person);

    $response = $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(collect($response->json('data.approvers'))->pluck('id')->all())->toBe([$active->id]);
});

it('AC-005: without superiors the active function managers other than the person approve', function () {
    $functionManager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person);
    $function = BusinessFunction::factory()->create(['manager_id' => $functionManager->id]);
    $person->businessFunctions()->attach($function->id);
    Sanctum::actingAs($person);

    $response = $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(collect($response->json('data.approvers'))->pluck('id')->all())->toBe([$functionManager->id]);
});

it('AC-005: a person managing their own function falls back to the manageAll holders, never to themselves', function () {
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $person = absenceUserWith(['viewAny', 'create', 'manageAll']);
    absenceProfile($person);
    $person->businessFunctions()->attach(BusinessFunction::factory()->create(['manager_id' => $person->id])->id);
    Sanctum::actingAs($person);

    $response = $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(collect($response->json('data.approvers'))->pluck('id')->all())->toBe([$hr->id]);
});

it('AC-005: a super-admin, who holds manageAll only through the role, is an HR-office approver', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $person = absenceUserWith();
    absenceProfile($person);
    Sanctum::actingAs($person);

    $response = $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(collect($response->json('data.approvers'))->pluck('id')->all())->toBe([$superAdmin->id]);
});

it('AC-005: a super-admin without superiors is approved by the other super-admins, never by themselves', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $requester = User::factory()->create();
    $requester->assignRole('super-admin');
    $otherSuperAdmin = User::factory()->create();
    $otherSuperAdmin->assignRole('super-admin');
    absenceProfile($requester);
    Sanctum::actingAs($requester);

    $response = $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(collect($response->json('data.approvers'))->pluck('id')->all())->toBe([$otherSuperAdmin->id]);
});

it('AC-005: nobody to approve is a 422 on user_id and nothing is left behind', function () {
    $person = absenceUserWith();
    absenceProfile($person);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('user_id');

    expect(AbsenceRequest::query()->count())->toBe(0);
});

it('AC-007: a type without approval goes straight to approved with decided_at and no decider', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    Sanctum::actingAs($person);

    $type = AbsenceType::factory()->withoutApproval()->create();
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => $type->id, 'submit' => true]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decided_by', null)
        ->assertJsonPath('data.approvers.0.id', $manager->id);

    expect(AbsenceRequest::query()->first()->decided_at)->not->toBeNull();
});

it('creation needs the create permission, an inactive type is refused, user_id of someone else needs manageAll', function () {
    $other = absenceUserWith();
    Sanctum::actingAs(absenceUserWith(['viewAny']));
    $this->postJson('/api/absence-requests', absencePayload())->assertForbidden();

    Sanctum::actingAs(absenceUserWith());
    $this->postJson('/api/absence-requests', absencePayload(['absence_type_id' => AbsenceType::factory()->inactive()->create()->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('absence_type_id');
    $this->postJson('/api/absence-requests', absencePayload(['user_id' => $other->id]))->assertForbidden();

    $hr = absenceUserWith(['viewAny', 'create', 'manageAll']);
    Sanctum::actingAs($hr);
    $this->postJson('/api/absence-requests', absencePayload(['user_id' => $other->id]))
        ->assertCreated()
        ->assertJsonPath('data.user.id', $other->id);

    expect(AbsenceRequest::query()->first()->created_by)->toBe($hr->id);
});
