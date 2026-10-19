<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceType;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-19 09:00:00');
    Notification::fake();
});

afterEach(fn () => Carbon::setTestNow());

function absenceNotifiedTo(User $user): bool
{
    return Notification::sent($user, AbsenceRequestNotification::class)->isNotEmpty();
}

it('AC-011: submitting notifies the approvers and never the person', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    expect(absenceNotifiedTo($manager))->toBeTrue()
        ->and(absenceNotifiedTo($person))->toBeFalse();
    Notification::assertSentTo($manager, AbsenceRequestNotification::class, function ($notification, $channels) use ($manager) {
        $payload = $notification->toArray($manager);

        return $channels === ['database', 'mail'] && str_starts_with((string) $payload['action_url'], '/absences?request=');
    });
});

it('AC-011: approving notifies the person only', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/approve")->assertOk();

    expect(absenceNotifiedTo($person))->toBeTrue()
        ->and(absenceNotifiedTo($manager))->toBeFalse();
});

it('AC-011: rejecting notifies the person with the reason', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/reject", ['rejection_reason' => 'Busy period'])->assertOk();

    Notification::assertSentTo($person, AbsenceRequestNotification::class, fn ($notification) => str_contains($notification->toArray($person)['message'], 'Busy period'));
    expect(absenceNotifiedTo($manager))->toBeFalse();
});

it('AC-011: the person cancelling a submitted request notifies the approvers, not themselves', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();

    expect(absenceNotifiedTo($manager))->toBeTrue()
        ->and(absenceNotifiedTo($person))->toBeFalse();
});

it('AC-011: HR cancelling an approved request notifies the person and the approvers, not HR', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $manager, ['status' => AbsenceRequestStatus::Approved]);
    Sanctum::actingAs($hr);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();

    expect(absenceNotifiedTo($person))->toBeTrue()
        ->and(absenceNotifiedTo($manager))->toBeTrue()
        ->and(absenceNotifiedTo($hr))->toBeFalse();
});

it('AC-011: cancelling a draft notifies nobody', function () {
    $person = absenceUserWith();
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $hr, ['status' => AbsenceRequestStatus::Draft]);
    Sanctum::actingAs($hr);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertStatus(409);

    Notification::assertNothingSent();
});

it('AC-011: a recipient without an email address gets the database channel only', function () {
    $manager = absenceUserWith();
    $manager->forceFill(['email' => ''])->save();
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();

    Notification::assertSentTo($manager, AbsenceRequestNotification::class, fn ($notification, $channels) => $channels === ['database']);
});

it('AC-011: no action_url for a recipient who cannot open the absences page', function () {
    $manager = absenceUserWith([]);
    $person = absenceUserWith();
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();

    Notification::assertSentTo($manager, AbsenceRequestNotification::class, fn ($notification) => $notification->toArray($manager)['action_url'] === null);
});

it('AC-007: a type without approval is approved at once and notifies the approvers (not the author)', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload([
        'absence_type_id' => AbsenceType::factory()->withoutApproval()->create()->id,
        'submit' => true,
    ]))->assertCreated()->assertJsonPath('data.status', 'approved');

    expect(absenceNotifiedTo($manager))->toBeTrue()
        ->and(absenceNotifiedTo($person))->toBeFalse();
});

it('AC-011: HR filing an automatic absence on behalf notifies the person and the approvers', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $hr = absenceUserWith(['viewAny', 'create', 'manageAll']);
    Sanctum::actingAs($hr);

    $this->postJson('/api/absence-requests', absencePayload([
        'user_id' => $person->id,
        'absence_type_id' => AbsenceType::factory()->withoutApproval()->create()->id,
        'submit' => true,
    ]))->assertCreated();

    expect(absenceNotifiedTo($person))->toBeTrue()
        ->and(absenceNotifiedTo($manager))->toBeTrue()
        ->and(absenceNotifiedTo($hr))->toBeFalse();
});
