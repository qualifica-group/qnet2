<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\FieldChangeRequestStatus;
use App\Enums\NotificationLevelEnum;
use App\Models\FieldChangeRequest;
use App\Models\User;
use App\Notifications\Concerns\QueuesMailSeparately;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\DetailFields;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the REQUESTER when their field-change-request is approved or
 * rejected (spec 0078, D-3/AC-026): `level` is `success`/`warning`
 * depending on the outcome, the message carries the handler's optional
 * `handling_note`. `$subjectLabel`/`$subjectPath` are precomputed by the
 * caller (FieldChangeRequestApprover/Rejecter), same reasoning as
 * FieldChangeRequestedNotification.
 */
class FieldChangeRequestResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    /**
     * @param  array<string, string>  $subjectDetails  the card of the record
     *                                                 the request is about
     *                                                 (RecordDetails), built
     *                                                 by the caller
     */
    public function __construct(
        private readonly FieldChangeRequest $fieldChangeRequest,
        private readonly User $handler,
        private readonly string $subjectLabel,
        private readonly string $subjectPath,
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
            title: $this->title(),
            message: $this->message(),
            level: $this->approved() ? NotificationLevelEnum::Success : NotificationLevelEnum::Warning,
            actionUrl: $this->subjectPath,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return RecordMail::compose(
            (new MailMessage)
                ->subject($this->title())
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->message(),
            $this->details(),
            $this->subjectPath,
        );
    }

    /**
     * Spec 0381: the outcome, then the record the request is about.
     *
     * @return array<string, string>
     */
    private function details(): array
    {
        return [
            ...DetailFields::compact([
                'notifications.fields.field' => $this->fieldChangeRequest->fieldLabel(),
                'notifications.fields.outcome' => $this->approved() ? 'notifications.values.approved' : 'notifications.values.rejected',
                'notifications.fields.note' => $this->fieldChangeRequest->handling_note,
                'notifications.fields.handled_by' => $this->handler->name,
            ]),
            ...$this->subjectDetails,
        ];
    }

    private function approved(): bool
    {
        return $this->fieldChangeRequest->status === FieldChangeRequestStatus::Approved;
    }

    private function title(): string
    {
        return $this->approved() ? __('Change request approved') : __('Change request rejected');
    }

    private function message(): string
    {
        $template = $this->approved()
            ? ':handler approved your request on :field of :subject'
            : ':handler rejected your request on :field of :subject';

        $message = __($template, [
            'handler' => $this->handler->name,
            'field' => $this->fieldChangeRequest->fieldLabel(),
            'subject' => $this->subjectLabel,
        ]);

        if ($this->fieldChangeRequest->handling_note === null || $this->fieldChangeRequest->handling_note === '') {
            return $message;
        }

        return $message.' '.__('Note: :note', ['note' => $this->fieldChangeRequest->handling_note]);
    }
}
