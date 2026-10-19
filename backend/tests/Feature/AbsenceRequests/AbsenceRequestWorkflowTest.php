<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-19 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('AC-006: an approver approves a submitted request and decided_by/decided_at are set', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decided_by.id', $manager->id);

    expect($request->fresh()->decided_at)->not->toBeNull();
});

it('AC-006: reject needs a reason (422), then stores it', function () {
    $manager = absenceUserWith();
    $request = absenceSubmitted(absenceUserWith(), $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');

    $this->postJson("/api/absence-requests/{$request->id}/reject", ['rejection_reason' => 'Busy period'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Busy period');
});

it('AC-006: only a submitted request can be decided (409)', function (string $state) {
    $manager = absenceUserWith();
    $request = absenceSubmitted(absenceUserWith(), $manager, ['status' => $state]);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/approve")->assertStatus(409);
    $this->postJson("/api/absence-requests/{$request->id}/reject", ['rejection_reason' => 'x'])->assertStatus(409);
})->with(['approved', 'rejected', 'cancelled']);

it('AC-006: a manageAll holder not among the approvers, and the person themselves, get 403', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith(['viewAny', 'create', 'manageAll']);
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $manager);

    foreach ([$hr, $person] as $actor) {
        Sanctum::actingAs($actor);
        $this->postJson("/api/absence-requests/{$request->id}/approve")->assertForbidden();
        $this->postJson("/api/absence-requests/{$request->id}/reject", ['rejection_reason' => 'x'])->assertForbidden();
    }

    expect($request->fresh()->status)->toBe(AbsenceRequestStatus::Submitted);
});

it('AC-008: cancellation follows D-9', function (string $actorKind, string $state, string $startsOn, int $expected) {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager, ['status' => $state, 'starts_on' => $startsOn, 'ends_on' => $startsOn]);
    Sanctum::actingAs($actorKind === 'hr' ? absenceUserWith(['viewAny', 'manageAll']) : $person);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertStatus($expected);
})->with([
    'person, submitted' => ['person', 'submitted', '2026-10-15', 200],
    'person, approved in the future' => ['person', 'approved', '2026-10-20', 200],
    'person, approved already started' => ['person', 'approved', '2026-10-19', 403],
    'hr, approved already started' => ['hr', 'approved', '2026-10-12', 200],
    'person, rejected' => ['person', 'rejected', '2026-12-14', 409],
    'person, cancelled' => ['person', 'cancelled', '2026-12-14', 409],
    'hr, rejected' => ['hr', 'rejected', '2026-12-14', 409],
]);

it('AC-008: cancelling records who and when, and an approver or stranger-in-sight gets 403', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);

    Sanctum::actingAs($manager);
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertForbidden();

    Sanctum::actingAs($person);
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

    $fresh = $request->fresh();
    expect($fresh->cancelled_by)->toBe($person->id)->and($fresh->cancelled_at)->not->toBeNull();
});

it('AC-009: edit and delete work on a draft, not on other states (409)', function () {
    $person = absenceUserWith();
    $draft = AbsenceRequest::factory()->forUser($person)->create(['absence_type_id' => AbsenceType::factory()->create()->id]);
    $submitted = AbsenceRequest::factory()->forUser($person)->submitted()->create(['absence_type_id' => $draft->absence_type_id, 'starts_on' => '2027-01-11', 'ends_on' => '2027-01-11']);
    Sanctum::actingAs($person);

    $this->patchJson("/api/absence-requests/{$draft->id}", absencePayload(['absence_type_id' => $draft->absence_type_id, 'starts_on' => '2026-12-15', 'ends_on' => '2026-12-16', 'note' => 'Changed']))
        ->assertOk()
        ->assertJsonPath('data.ends_on', '2026-12-16')
        ->assertJsonPath('data.note', 'Changed');

    $this->patchJson("/api/absence-requests/{$submitted->id}", absencePayload())->assertStatus(409);
    $this->deleteJson("/api/absence-requests/{$submitted->id}")->assertStatus(409);
    $this->postJson("/api/absence-requests/{$submitted->id}/submit")->assertStatus(409);

    $this->deleteJson("/api/absence-requests/{$draft->id}")->assertNoContent();
    expect(AbsenceRequest::query()->find($draft->id))->toBeNull();
});

it('AC-009: a viewer who is not the person nor manageAll gets 403 on edit, delete and submit of a draft', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $draft = AbsenceRequest::factory()->forUser($person)->create(['absence_type_id' => AbsenceType::factory()->create()->id]);
    Sanctum::actingAs($manager);

    $this->patchJson("/api/absence-requests/{$draft->id}", absencePayload())->assertForbidden();
    $this->deleteJson("/api/absence-requests/{$draft->id}")->assertForbidden();
    $this->postJson("/api/absence-requests/{$draft->id}/submit")->assertForbidden();
});

it('AC-009: a stranger gets 404 on show, patch, delete and every action, an HR office user sees it', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs(absenceUserWith());

    $this->getJson("/api/absence-requests/{$request->id}")->assertNotFound();
    $this->patchJson("/api/absence-requests/{$request->id}", [])->assertNotFound();
    $this->deleteJson("/api/absence-requests/{$request->id}")->assertNotFound();
    foreach (['submit', 'approve', 'cancel'] as $action) {
        $this->postJson("/api/absence-requests/{$request->id}/{$action}")->assertNotFound();
    }
    $this->postJson("/api/absence-requests/{$request->id}/reject", [])->assertNotFound();

    Sanctum::actingAs(absenceUserWith(['viewAny', 'manageAll']));
    $this->getJson("/api/absence-requests/{$request->id}")->assertOk();
});

it('AC-009: a superior of the person sees the request, the can block follows the actor', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $request = absenceSubmitted($person, absenceUserWith());

    Sanctum::actingAs($manager);
    $this->getJson("/api/absence-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.can.approve', false)
        ->assertJsonPath('data.can.cancel', false);

    Sanctum::actingAs($person);
    $this->getJson("/api/absence-requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.can.cancel', true)
        ->assertJsonPath('data.can.attach', true)
        ->assertJsonPath('data.can.update', false);
});

it('AC-017: creation, draft edit and every status change are logged with who, what and the old value', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);

    Sanctum::actingAs($person);
    $id = $this->postJson('/api/absence-requests', absencePayload(['note' => 'First']))->json('data.id');
    $this->patchJson("/api/absence-requests/{$id}", absencePayload(['absence_type_id' => AbsenceRequest::find($id)->absence_type_id, 'note' => 'Second']))->assertOk();
    $this->postJson("/api/absence-requests/{$id}/submit")->assertOk();

    Sanctum::actingAs($manager);
    $this->postJson("/api/absence-requests/{$id}/approve")->assertOk();

    $logs = AbsenceRequest::find($id)->activities()->oldest('id')->get();

    expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'updated', 'updated'])
        ->and($logs[0]->causer_id)->toBe($person->id)
        ->and($logs[0]->properties['attributes']['note'])->toBe('First')
        ->and($logs[1]->properties['old']['note'])->toBe('First')
        ->and($logs[1]->properties['attributes']['note'])->toBe('Second')
        ->and($logs[2]->properties['old']['status'])->toBe('draft')
        ->and($logs[2]->properties['attributes']['status'])->toBe('submitted')
        ->and($logs[3]->causer_id)->toBe($manager->id)
        ->and($logs[3]->properties['old']['status'])->toBe('submitted')
        ->and($logs[3]->properties['attributes']['status'])->toBe('approved');
});
