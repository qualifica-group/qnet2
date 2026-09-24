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
 * Sent to the fixed `target` group "Richiedi aggiornamento" resolves (spec
 * 0153, D-14, superseding spec 0118 D-10..D-13's caller-picked recipient
 * list). Modelled on NoteMentionNotification: a DEDICATED `database`+`mail`
 * class, `ShouldQueue`, payload via NotificationData, deep-link resolved PER
 * RECIPIENT.
 *
 * The constructor takes only what TaskActionService already holds — the
 * Task, the requesting actor, the now-REQUIRED free-text message, and
 * `$isCc` (D-14: the CC group is a SEPARATE notification instance so the
 * stored `data.is_cc` distinguishes the two on read) — and never queries for
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
        private readonly string $message,
        private readonly bool $isCc = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null, is_cc: bool}
     */
    public function toArray(object $notifiable): array
    {
        $path = $this->pathFor($notifiable);

        return [
            ...(new NotificationData(
                title: __('Update requested'),
                message: $this->body($path),
                level: NotificationLevelEnum::Info,
                actionUrl: $path,
            ))->toArray(),
            'is_cc' => $this->isCc,
        ];
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
     * D-14: the message is now REQUIRED. The body always names the requester
     * and the Task (AC-053), then appends the message, truncated defensively.
     */
    private function body(?string $path): string
    {
        $body = __(':requester asked for an update on :title', [
            'requester' => $this->requester->name,
            'title' => $this->task->title,
        ]).' '.Str::limit($this->message, self::MESSAGE_LIMIT);

        if ($path !== null) {
            return $body;
        }

        return $body.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
