<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskNotable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to every recipient spelled out by the actor on "Richiedi
 * aggiornamento" (spec 0118, D-10..D-13) — never a default audience, since
 * D-11 forbids any automatic delivery. Modelled on NoteMentionNotification:
 * a DEDICATED `database`+`mail` class, `ShouldQueue`, payload via
 * NotificationData, deep-link resolved PER RECIPIENT.
 *
 * The constructor takes only what TaskActionService already holds — the
 * Task, the requesting actor, the free-text message — and never queries for
 * facts the caller could pass in.
 *
 * `action_url` is a PATH INTERNO, never absolute, resolved through
 * TaskNotable::deepLinkPath() (D-13): a recipient outside the Task's
 * visibility scope gets `null` and no mail button, never a link that lands
 * on a 403.
 */
class TaskUpdateRequested extends Notification implements ShouldQueue
{
    use Queueable;

    private const int MESSAGE_LIMIT = 2000;

    public function __construct(
        private readonly Task $task,
        private readonly User $requester,
        private readonly ?string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        $path = $this->pathFor($notifiable);

        return (new NotificationData(
            title: __('Update requested'),
            message: $this->body($path),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        $mail = (new MailMessage)
            ->subject(__('Update requested'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->body($path));

        // No reachable screen, no button: a CTA that lands on a 403 is worse
        // than none, and the body already says what to ask for (AC-057).
        if ($path !== null) {
            $mail->action(__('Open'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    /**
     * Resolved here, not by the caller: which screen this Task shows (or
     * whether it shows one at all) depends on the recipient's own
     * abilities, and D-11 sends this to several recipients at once.
     */
    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return app(TaskNotable::class)->deepLinkPath($this->task, $notifiable, null);
    }

    /**
     * D-12: the message is optional. Whether or not one was submitted, the
     * body always names the requester and the Task (AC-053); when a message
     * is present it is appended, truncated defensively.
     */
    private function body(?string $path): string
    {
        $body = __(':requester asked for an update on :title', [
            'requester' => $this->requester->name,
            'title' => $this->task->title,
        ]);

        if ($this->message !== null && $this->message !== '') {
            $body .= ' '.Str::limit($this->message, self::MESSAGE_LIMIT);
        }

        if ($path !== null) {
            return $body;
        }

        return $body.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
