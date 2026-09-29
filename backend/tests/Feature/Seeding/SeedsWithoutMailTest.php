<?php

use App\Models\User;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

// User directive 2026-09-29: a seed leaves in-app notifications, never emails.
uses(RefreshDatabase::class);

function seedWithoutMail(callable $seed): void
{
    (new class
    {
        use SeedsWithoutMail;

        public function run(callable $seed): void
        {
            $this->withoutMail($seed);
        }
    })->run($seed);
}

function queuedMailAndDatabaseNotification(): BaseNotification
{
    return new class extends BaseNotification implements ShouldQueue
    {
        public function via(object $notifiable): array
        {
            return ['database', 'mail'];
        }

        public function toArray(object $notifiable): array
        {
            return ['title' => 'Seeded'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->line('Seeded');
        }
    };
}

it('delivers the database channel and drops the mail channel', function (): void {
    $user = User::factory()->create();
    $mailsSent = 0;
    Event::listen(MessageSending::class, function () use (&$mailsSent): void {
        $mailsSent++;
    });

    seedWithoutMail(fn () => $user->notify(queuedMailAndDatabaseNotification()));

    expect($user->notifications()->count())->toBe(1)
        ->and($mailsSent)->toBe(0);
});

it('sends queued notifications in-process, so no mail job reaches a worker', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    seedWithoutMail(fn () => $user->notify(queuedMailAndDatabaseNotification()));

    Queue::assertNothingPushed();
    expect($user->notifications()->count())->toBe(1);
});

it('restores the previous notification dispatcher afterwards', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    seedWithoutMail(fn () => null);
    $user->notify(queuedMailAndDatabaseNotification());

    Notification::assertCount(1);
});
