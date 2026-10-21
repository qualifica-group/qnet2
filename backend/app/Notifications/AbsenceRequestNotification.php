<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification for every step of an absence request (spec 0278): the event
 * (submitted | approved | rejected | cancelled) and the audience (the person,
 * an approver, or the HR office in copy) only pick a sentence. Facts are precomputed by
 * AbsenceRequestNotifier; the link is resolved per recipient (no button for
 * someone who cannot open the page). Mail is skipped without an address.
 */
class AbsenceRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const string EVENT_SUBMITTED = 'submitted';

    public const string EVENT_APPROVED = 'approved';

    public const string EVENT_REJECTED = 'rejected';

    public const string EVENT_CANCELLED = 'cancelled';

    public function __construct(
        private readonly string $event,
        private readonly int $requestId,
        private readonly int $personId,
        private readonly string $personName,
        private readonly string $typeName,
        private readonly string $period,
        private readonly string $actorName,
        private readonly bool $automatic = false,
        private readonly ?string $reason = null,
        private readonly bool $copy = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationData(
            title: __("absence_requests.notification.{$this->event}.title"),
            message: $this->message($notifiable),
            level: $this->event === self::EVENT_REJECTED ? NotificationLevelEnum::Warning : NotificationLevelEnum::Info,
            actionUrl: $this->pathFor($notifiable),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__("absence_requests.notification.{$this->event}.title"))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message($notifiable));

        $path = $this->pathFor($notifiable);

        if ($path !== null) {
            $mail->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return $notifiable->can('absence-requests.viewAny') ? "/absences?request={$this->requestId}" : null;
    }

    private function message(object $notifiable): string
    {
        $audience = match (true) {
            $this->copy => 'office',
            (int) $notifiable->id === $this->personId => 'person',
            default => 'approver',
        };
        $key = $this->event === self::EVENT_APPROVED && $this->automatic
            ? "absence_requests.notification.approved.automatic_{$audience}"
            : "absence_requests.notification.{$this->event}.{$audience}";

        $message = __($key, [
            'person' => $this->personName,
            'type' => $this->typeName,
            'period' => $this->period,
            'actor' => $this->actorName,
        ]);

        return $this->reason === null
            ? $message
            : $message.' '.__('absence_requests.notification.reason', ['reason' => $this->reason]);
    }
}
