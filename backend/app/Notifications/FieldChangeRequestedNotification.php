<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\FieldChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every titolare of `field-change-requests.viewAny` when a NEW
 * request is created (spec 0078, AC-024), excluded the requester themselves
 * (SentTo/exclusion handled by the caller, FieldChangeRequestCreator).
 * Modelled on NoteMentionNotification (database + mail, ShouldQueue): the
 * database payload goes through the SAME NotificationData shape (title,
 * message, level, action_url), so the campanella/NotificationResource/unread
 * counter work unchanged.
 *
 * `$fieldLabel`/`$subjectLabel` are precomputed by the caller (never
 * resolved here): a Notification class should stay a thin presentation of
 * already-known facts, not a second place that talks to TableRegistry.
 */
class FieldChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FieldChangeRequest $fieldChangeRequest,
        private readonly User $requester,
        private readonly string $fieldLabel,
        private readonly string $subjectLabel,
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
        return (new NotificationData(
            title: __('New change request'),
            message: $this->message(),
            level: NotificationLevelEnum::Info,
            actionUrl: $this->dataActionUrl(),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New change request'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message())
            ->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$this->dataActionUrl());
    }

    /**
     * A path only (never an absolute URL, contract-frozen): the campanella's
     * own navigation prepends its own origin, and an absolute value here
     * would violate the "internal path only" constraint every action_url in
     * this app must satisfy.
     */
    private function dataActionUrl(): string
    {
        return "/field-change-requests/{$this->fieldChangeRequest->id}";
    }

    private function message(): string
    {
        return __(':requester requests to change :field of :subject from :current to :requested', [
            'requester' => $this->requester->name,
            'field' => $this->fieldLabel,
            'subject' => $this->subjectLabel,
            'current' => $this->fieldChangeRequest->current_label ?? '—',
            'requested' => $this->fieldChangeRequest->requested_label ?? '—',
        ]);
    }
}
