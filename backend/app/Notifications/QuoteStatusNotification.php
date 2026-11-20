<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\AssignmentTargetEnum;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use App\Notifications\Concerns\QueuesMailSeparately;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\RecordLinkResolver;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An offer entered a working status that sends an email (spec 0419): the
 * subject and message written in the status configurator, followed by the
 * event sentence, the offer's card and the button. A user gets the bell and,
 * per their preference, the email; a free address gets the email only,
 * without the internal card.
 *
 * Every fact is precomputed by QuoteStatusNotifier; only the link depends on
 * the recipient's permissions.
 */
class QuoteStatusNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    /**
     * @param  array<string, string>  $recordDetails  the offer's detail card (users only)
     */
    public function __construct(
        private readonly string $subject,
        private readonly string $message,
        private readonly string $statusName,
        private readonly string $recordLabel,
        private readonly int $quoteId,
        private readonly string $actorName,
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
        return (new NotificationData(
            title: $this->subject,
            message: $this->sentence(),
            level: NotificationLevelEnum::Info,
            actionUrl: $this->pathFor($notifiable),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isUser = $notifiable instanceof User;

        return RecordMail::composeWithBody(
            (new MailMessage)->subject($this->subject),
            nl2br(e($this->message), false),
            $this->sentence(),
            $isUser ? $this->recordDetails : [],
            $this->pathFor($notifiable),
        );
    }

    private function pathFor(object $notifiable): ?string
    {
        return $notifiable instanceof User
            ? RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::Quote, $this->quoteId)
            : null;
    }

    private function sentence(): string
    {
        return __(':actor moved :record to the status ":status".', [
            'actor' => $this->actorName,
            'record' => $this->recordLabel,
            'status' => $this->statusName,
        ]);
    }
}
