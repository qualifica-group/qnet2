<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\FieldChangeRequestStatus;
use App\Enums\NotificationLevelEnum;
use App\Models\FieldChangeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the REQUESTER when their field-change-request is approved or
 * rejected (spec 0078, D-3/AC-026): `level` is `success`/`warning`
 * depending on the outcome, the message carries the handler's optional
 * `handling_note`. `$fieldLabel`/`$subjectLabel`/`$subjectPath` are
 * precomputed by the caller (FieldChangeRequestApprover/Rejecter), same
 * reasoning as FieldChangeRequestedNotification.
 */
class FieldChangeRequestResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FieldChangeRequest $fieldChangeRequest,
        private readonly User $handler,
        private readonly string $fieldLabel,
        private readonly string $subjectLabel,
        private readonly string $subjectPath,
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
            title: $this->title(),
            message: $this->message(),
            level: $this->approved() ? NotificationLevelEnum::Success : NotificationLevelEnum::Warning,
            actionUrl: $this->subjectPath,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message())
            ->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$this->subjectPath);
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
        $outcome = $this->approved() ? __('approved') : __('rejected');

        $message = __(':handler has :outcome your request on :field of :subject', [
            'handler' => $this->handler->name,
            'outcome' => $outcome,
            'field' => $this->fieldLabel,
            'subject' => $this->subjectLabel,
        ]);

        if ($this->fieldChangeRequest->handling_note === null || $this->fieldChangeRequest->handling_note === '') {
            return $message;
        }

        return $message.' '.__('Note: :note', ['note' => $this->fieldChangeRequest->handling_note]);
    }
}
