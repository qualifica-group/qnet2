<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use App\Notifications\FieldChangeRequestedNotification;
use App\Notifications\FieldChangeRequestResolvedNotification;
use App\Notifications\NoteMentionNotification;
use App\Notifications\PurchaseRequestSubmittedNotification;
use App\Notifications\RecordAssignmentNotification;
use App\Notifications\RequestTransferredNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskUpdateRequested;
use App\Notifications\WelcomeUserNotification;
use App\Notifications\WorkOrderLineDeliverableNotification;
use App\Notifications\WorkOrderTasksAssignedNotification;
use App\Notifications\WorkOrderUpdateRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Per-user "email notifications" switch (settings page). On: every in-app
 * notification also goes out by email. Off: only the bell entry is written.
 * Credential emails (welcome, password reset) are never affected.
 */
const GATED_NOTIFICATIONS = [
    AbsenceRequestNotification::class,
    FieldChangeRequestedNotification::class,
    FieldChangeRequestResolvedNotification::class,
    NoteMentionNotification::class,
    PurchaseRequestSubmittedNotification::class,
    RecordAssignmentNotification::class,
    RequestTransferredNotification::class,
    TaskAssigned::class,
    TaskUpdateRequested::class,
    WorkOrderLineDeliverableNotification::class,
    WorkOrderTasksAssignedNotification::class,
    WorkOrderUpdateRequested::class,
];

/** via() reads only the recipient, so the payload is irrelevant here. */
function channelsFor(string $class, User $user): array
{
    return (new ReflectionClass($class))->newInstanceWithoutConstructor()->via($user);
}

it('GET /auth/me reports the emails as on for a new user', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email_notifications_enabled', true);
});

it('PATCH /auth/me turns the emails off and back on', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['email_notifications_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.email_notifications_enabled', false);
    expect($user->fresh()->email_notifications_enabled)->toBeFalse();

    $this->patchJson('/api/auth/me', ['email_notifications_enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.email_notifications_enabled', true);
    expect($user->fresh()->email_notifications_enabled)->toBeTrue();
});

it('omitting the key leaves the stored switch untouched', function () {
    Sanctum::actingAs(User::factory()->create(['email_notifications_enabled' => false]));

    $this->patchJson('/api/auth/me', ['locale' => 'it'])
        ->assertOk()
        ->assertJsonPath('data.email_notifications_enabled', false);
});

it('rejects a value that is not a boolean', function (mixed $value) {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', ['email_notifications_enabled' => $value])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email_notifications_enabled');

    expect($user->fresh()->email_notifications_enabled)->toBeTrue();
})->with(['yes', null, 5]);

it('rejects an unauthenticated request', function () {
    $this->patchJson('/api/auth/me', ['email_notifications_enabled' => false])->assertUnauthorized();
});

it('only ever touches the authenticated user', function () {
    $other = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/auth/me', ['email_notifications_enabled' => false])->assertOk();

    expect($other->fresh()->email_notifications_enabled)->toBeTrue();
});

it('sends bell and email when the switch is on', function (string $class) {
    expect(channelsFor($class, User::factory()->create()))->toBe(['database', 'mail']);
})->with(GATED_NOTIFICATIONS);

it('sends only the bell entry when the switch is off', function (string $class) {
    $user = User::factory()->create(['email_notifications_enabled' => false]);

    expect(channelsFor($class, $user))->toBe(['database']);
})->with(GATED_NOTIFICATIONS);

it('sends only the bell entry when the user has no email address', function (string $class) {
    $user = User::factory()->make(['email' => null]);

    expect(channelsFor($class, $user))->toBe(['database']);
})->with(GATED_NOTIFICATIONS);

it('still sends the credential emails with the switch off', function (string $class) {
    $user = User::factory()->create(['email_notifications_enabled' => false]);

    expect(channelsFor($class, $user))->toBe(['mail']);
})->with([WelcomeUserNotification::class, ResetPasswordNotification::class]);

it('a real dispatch to an opted-out user writes the bell entry only', function () {
    Notification::fake();
    $user = User::factory()->create(['email_notifications_enabled' => false]);

    $user->notify(new TaskAssigned(Task::factory()->create(), null));

    Notification::assertSentTo(
        $user,
        TaskAssigned::class,
        fn (TaskAssigned $notification, array $channels): bool => $channels === ['database'],
    );
});
