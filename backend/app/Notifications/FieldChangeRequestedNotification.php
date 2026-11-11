<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\DetailFields;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the active holders of FieldChangeRequestCreator::NOTIFIED_ROLE when
 * a NEW request is created (spec 0078, AC-024/D-8), excluded the requester themselves
 * (SentTo/exclusion handled by the caller, FieldChangeRequestCreator).
 * Modelled on NoteMentionNotification (database + mail, ShouldQueue): the
 * database payload goes through the SAME NotificationData shape (title,
 * message, level, action_url), so the campanella/NotificationResource/unread
 * counter work unchanged.
 *
 * `$subjectLabel` is precomputed by the caller (never resolved here): a
 * Notification class should stay a thin presentation of already-known facts,
 * not a second place that talks to TableRegistry. The field label is instead
 * translated at render time, so it follows the recipient's locale.
 */
class FieldChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsEmailPreference;

    /**
     * @param  array<string, string>  $subjectDetails  the card of the record
     *                                                 the request is about
     *                                                 (RecordDetails), built
     *                                                 by the caller
     */
    public function __construct(
        private readonly FieldChangeRequest $fieldChangeRequest,
        private readonly User $requester,
        private readonly string $subjectLabel,
        private readonly array $subjectDetails = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->databaseAndPreferredMail($notifiable);
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
        return RecordMail::compose(
            (new MailMessage)
                ->subject(__('New change request'))
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->message(),
            $this->details(),
            $this->dataActionUrl(),
        );
    }

    /**
     * Spec 0381: the change asked for, then the record it is about.
     *
     * @return array<string, string>
     */
    private function details(): array
    {
        return [
            ...DetailFields::compact([
                'notifications.fields.field' => $this->fieldChangeRequest->fieldLabel(),
                'notifications.fields.current_value' => $this->fieldChangeRequest->current_label ?? __('notifications.values.empty'),
                'notifications.fields.requested_value' => $this->fieldChangeRequest->requested_label ?? __('notifications.values.empty'),
                'notifications.fields.requester' => $this->requester->name,
            ]),
            ...$this->subjectDetails,
        ];
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
            'field' => $this->fieldChangeRequest->fieldLabel(),
            'subject' => $this->subjectLabel,
            'current' => $this->fieldChangeRequest->current_label ?? '—',
            'requested' => $this->fieldChangeRequest->requested_label ?? '—',
        ]);
    }
}
