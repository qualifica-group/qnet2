<?php

use App\Models\User;
use App\Notifications\TableRowNotification;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * The email copy of a notification travels on its own queue, so an outage of
 * the mail provider stalls only that queue: the bell entries and every other
 * job on `default` keep flowing.
 */
function tableRowNotification(): TableRowNotification
{
    return new TableRowNotification('Field', null, 'Record', null, 1, 'Actor', null, [], []);
}

it('routes the mail channel to the dedicated queue and keeps the bell on the default one', function () {
    Queue::fake();
    $user = User::factory()->create(['email_notifications_enabled' => true]);

    $user->notify(tableRowNotification());

    Queue::assertPushedOn(config('mail.notifications_queue'), SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job) => $job->channels === ['mail']);
    Queue::assertPushed(SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job) => $job->channels === ['database'] && $job->queue === null);
});

it('routes a mail-only credential email to the dedicated queue', function () {
    Queue::fake();
    $user = User::factory()->create();

    $user->notify(new WelcomeUserNotification('secret-password'));

    Queue::assertPushedOn(config('mail.notifications_queue'), SendQueuedNotifications::class);
});

it('sends the email of every queued notification on the dedicated queue', function () {
    $queued = collect(glob(app_path('Notifications/*.php')))
        ->map(fn (string $path) => 'App\\Notifications\\'.basename($path, '.php'))
        ->map(fn (string $class) => new ReflectionClass($class))
        ->filter(fn (ReflectionClass $class) => ! $class->isAbstract()
            && $class->implementsInterface(ShouldQueue::class));

    expect($queued)->not->toBeEmpty();

    $queued->each(function (ReflectionClass $class) {
        $notification = $class->newInstanceWithoutConstructor();

        expect(method_exists($notification, 'viaQueues'))->toBeTrue($class->getName().' has no viaQueues()')
            ->and($notification->viaQueues()['mail'] ?? null)->toBe(config('mail.notifications_queue'));
    });
});
