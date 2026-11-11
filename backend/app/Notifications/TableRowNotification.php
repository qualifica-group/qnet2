<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\AssignmentTargetEnum;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\RecordLinkResolver;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A row of a flexible `table` field fired its event (spec 0390): a row was
 * added, or a yes/no column was switched on. A user gets the bell and, per
 * their preference, the email (D-4); a free address configured on the field
 * gets the email only, with the row's values but not the internal record card.
 *
 * Every fact is precomputed by TableRowNotifier (the chosen "Modello email"
 * already rendered against the record); only the link depends on the
 * recipient's permissions.
 */
class TableRowNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsEmailPreference;

    /**
     * @param  ?string  $triggerLabel  label of the yes/no column that fired, null when a row was added
     * @param  ?AssignmentTargetEnum  $target  the module the record opens in, null when it has none
     * @param  ?array{subject: string, body: string}  $email  the rendered template, null = default email
     * @param  array<string, string>  $rowDetails  the row's cells
     * @param  array<string, string>  $recordDetails  the record's detail card (users only)
     */
    public function __construct(
        private readonly string $fieldName,
        private readonly ?string $triggerLabel,
        private readonly string $recordLabel,
        private readonly ?AssignmentTargetEnum $target,
        private readonly int $recordId,
        private readonly string $actorName,
        private readonly ?array $email,
        private readonly array $rowDetails,
        private readonly array $recordDetails,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $this->databaseAndPreferredMail($notifiable) : ['mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        $path = $this->pathFor($notifiable);

        return (new NotificationData(
            title: $this->title(),
            message: $this->sentence($notifiable, $path),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);
        $isUser = $notifiable instanceof User;
        $mail = (new MailMessage)->subject($this->title());
        $details = $isUser ? [...$this->rowDetails, ...$this->recordDetails] : $this->rowDetails;
        $sentence = $this->sentence($notifiable, $path);

        if ($this->email !== null) {
            return RecordMail::composeWithBody($mail, $this->email['body'], $sentence, $details, $path);
        }

        if ($isUser) {
            $mail->greeting(__('Hello :name,', ['name' => $notifiable->name]));
        }

        return RecordMail::compose($mail, $sentence, $details, $path);
    }

    private function pathFor(object $notifiable): ?string
    {
        if ($this->target === null || ! $notifiable instanceof User) {
            return null;
        }

        return RecordLinkResolver::pathFor($notifiable, $this->target, $this->recordId);
    }

    private function title(): string
    {
        return $this->email['subject'] ?? "{$this->fieldName} - {$this->recordLabel}";
    }

    private function sentence(object $notifiable, ?string $path): string
    {
        $replace = [
            'actor' => $this->actorName,
            'field' => $this->fieldName,
            'column' => (string) $this->triggerLabel,
            'record' => $this->recordLabel,
        ];
        $sentence = $this->triggerLabel === null
            ? __(':actor added a row to ":field" on :record.', $replace)
            : __(':actor turned on ":column" on a row of ":field" on :record.', $replace);

        if ($this->target === null || $path !== null || ! $notifiable instanceof User) {
            return $sentence;
        }

        return $sentence.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
