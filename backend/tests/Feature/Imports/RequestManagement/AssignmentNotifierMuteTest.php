<?php

use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('muted() silences notify() only inside the callback and restores the state even when it throws', function () {
    Notification::fake();
    $opportunity = Opportunity::factory()->create();
    $supervisor = User::factory()->create();
    $notify = fn () => app(AssignmentNotifier::class)->notify($opportunity, null, $supervisor->id, []);

    AssignmentNotifier::muted($notify);
    Notification::assertNothingSent();

    try {
        AssignmentNotifier::muted(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    $notify();
    Notification::assertSentTo($supervisor, RecordAssignmentNotification::class);
});
