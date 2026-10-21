<?php

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceType;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

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

// REQUIREMENT CHANGED (spec 0278 D-15): the HR office may decide, so its copy reads as one to approve.
it('AC-011: the HR office gets a copy of a submission, to approve like the manager', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    Notification::assertSentToTimes($hr, AbsenceRequestNotification::class, 1);
    Notification::assertSentTo($hr, AbsenceRequestNotification::class, function ($notification, $channels) use ($hr, $person) {
        $payload = $notification->toArray($hr);

        return $channels === ['database', 'mail']
            && $payload['title'] === __('absence_requests.notification.submitted.title')
            && str_contains($payload['message'], $person->name)
            && str_starts_with((string) $payload['action_url'], '/absences?request=');
    });
    Notification::assertSentTo($manager, AbsenceRequestNotification::class, fn ($notification) => $notification->toArray($manager)['title'] === __('absence_requests.notification.submitted.title'));
});

it('AC-011: the HR office gets a copy of the decision, with the rejection reason', function (string $action, array $payload, string $event) {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/{$action}", $payload)->assertOk();

    Notification::assertSentTo($hr, AbsenceRequestNotification::class, function ($notification) use ($hr, $manager, $payload, $event) {
        $message = $notification->toArray($hr)['message'];

        return $notification->toArray($hr)['title'] === __("absence_requests.notification.{$event}.title")
            && str_contains($message, $manager->name)
            && str_contains($message, $payload['rejection_reason'] ?? '');
    });
    expect(absenceNotifiedTo($manager))->toBeFalse();
})->with([
    'approve' => ['approve', [], 'approved'],
    'reject' => ['reject', ['rejection_reason' => 'Busy period'], 'rejected'],
]);

it('AC-011: the HR office gets a copy of a cancellation and of an automatic absence', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $manager);
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload([
        'absence_type_id' => AbsenceType::factory()->withoutApproval()->create()->id,
        'submit' => true,
    ]))->assertCreated();
    $request = absenceSubmitted($person, $manager, ['starts_on' => '2026-12-21', 'ends_on' => '2026-12-21']);
    $this->postJson("/api/absence-requests/{$request->id}/cancel")->assertOk();

    Notification::assertSentToTimes($hr, AbsenceRequestNotification::class, 2);
});

it('AC-011: an HR approver (last resort) is notified once, as the approver', function () {
    $person = absenceUserWith();
    absenceProfile($person);
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    Sanctum::actingAs($person);

    $this->postJson('/api/absence-requests', absencePayload(['submit' => true]))->assertCreated();

    Notification::assertSentToTimes($hr, AbsenceRequestNotification::class, 1);
    Notification::assertSentTo($hr, AbsenceRequestNotification::class, fn ($notification) => $notification->toArray($hr)['title'] === __('absence_requests.notification.submitted.title'));
});

it('AC-011: no copy to an inactive HR user, to a super-admin outside HR, or to an HR person on their own request', function () {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $inactiveHr = absenceUserWith(['viewAny', 'manageAll']);
    $inactiveHr->forceFill(['is_active' => false])->save();
    $manager = absenceUserWith();
    $hrPerson = absenceUserWith(['viewAny', 'create', 'manageAll']);
    $request = absenceSubmitted($hrPerson, $manager);
    Sanctum::actingAs($manager);

    $this->postJson("/api/absence-requests/{$request->id}/approve")->assertOk();

    expect(absenceNotifiedTo($superAdmin))->toBeFalse()
        ->and(absenceNotifiedTo($inactiveHr))->toBeFalse();
    Notification::assertSentToTimes($hrPerson, AbsenceRequestNotification::class, 1);
    $expected = __('absence_requests.notification.approved.person', ['type' => $request->absenceType->name, 'period' => '2026-12-14', 'actor' => $manager->name]);
    Notification::assertSentTo($hrPerson, AbsenceRequestNotification::class, fn ($notification) => $notification->toArray($hrPerson)['message'] === $expected);
});

it('AC-011: an HR decision tells the person and the manager, who did not decide (D-15)', function () {
    $manager = absenceUserWith();
    $person = absenceUserWith();
    $hr = absenceUserWith(['viewAny', 'manageAll']);
    $request = absenceSubmitted($person, $manager);
    Sanctum::actingAs($hr);

    $this->postJson("/api/absence-requests/{$request->id}/reject", ['rejection_reason' => 'Busy period'])->assertOk();

    $expected = __('absence_requests.notification.rejected.approver', ['person' => $person->name, 'type' => $request->absenceType->name, 'period' => '2026-12-14', 'actor' => $hr->name])
        .' '.__('absence_requests.notification.reason', ['reason' => 'Busy period']);
    Notification::assertSentTo($manager, AbsenceRequestNotification::class, fn ($notification) => $notification->toArray($manager)['message'] === $expected);
    expect(absenceNotifiedTo($person))->toBeTrue()
        ->and(absenceNotifiedTo($hr))->toBeFalse();
});
