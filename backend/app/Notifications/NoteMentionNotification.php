<?php

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\Note;
use App\Models\User;
use App\Notes\Mentions\MentionParser;
use App\Notes\NoteEntityRegistry;
use App\Notifications\Concerns\QueuesMailSeparately;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\RecordDetails;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every NEW @mention on a note (spec 0052, D-11) — never to the
 * author, never twice for the same user/note. A DEDICATED class (not
 * GenericNotification) because it carries its own data (note, author, host
 * record label/link) and needs the `mail` channel on top of `database`.
 *
 * The database payload still goes through NotificationData (title, message,
 * level, action_url): the campanella, NotificationResource and the unread
 * counter work unchanged (constraints: no touching NotificationService/
 * NotificationResource). Since D-10 already guarantees the recipient can
 * read the host record, the message MAY carry a body excerpt without
 * over-disclosure.
 *
 * `action_url` is a PATH INTERNO, never absolute, and is resolved PER
 * RECIPIENT — the same two rules RecordAssignmentNotification and
 * RequestTransferredNotification already follow (spec 0081). It used to be
 * precomputed once by the caller as an absolute URL, which the campanella
 * rejected outright (`safeInternalPath()`), leaving every mention row
 * unclickable.
 */
class NoteMentionNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    private const int EXCERPT_LENGTH = 140;

    private const string NO_ACCESS_HINT = 'You cannot open this record: ask an administrator to grant you access to the module.';

    /**
     * @param  string  $entityType  the `notable_types` slug of $record, the
     *                              only thing the link resolution needs on
     *                              top of the recipient's own abilities
     * @param  Model  $record  the note's host record, passed in rather than
     *                         read back off `$note->notable`: a Notification
     *                         presents facts the caller already holds, it
     *                         does not query for them
     * @param  array<int, int>  $enlistedUserIds  recipients this save added to the record (spec 0353, D-7)
     * @param  string|null  $enlistedRole  the role they were given, as named by the host module
     */
    public function __construct(
        private readonly Note $note,
        private readonly User $author,
        private readonly string $recordLabel,
        private readonly string $entityType,
        private readonly Model $record,
        private readonly array $enlistedUserIds = [],
        private readonly ?string $enlistedRole = null,
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
        $path = $this->pathFor($notifiable);

        return (new NotificationData(
            title: __('You were mentioned'),
            message: $this->message($path, $notifiable),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        // No reachable screen, no button: a CTA that lands on a 403 is worse
        // than none, and the message already says what to ask for.
        return RecordMail::compose(
            (new MailMessage)
                ->subject(__('You were mentioned'))
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->mailLines($path, $notifiable),
            $this->details(),
            $path,
        );
    }

    /**
     * The email names no record: its detail card already carries the title,
     * and a title quoted inline ran into the note, so the reader could not
     * tell where one ended and the other began. The note gets its own quoted
     * paragraph instead.
     *
     * @return array<int, string>
     */
    private function mailLines(?string $path, object $notifiable): array
    {
        $params = ['author' => $this->author->name];

        $lines = [
            $this->wasEnlisted($notifiable)
                ? __(':author mentioned you and added you as :role:', $params + ['role' => __(ucfirst((string) $this->enlistedRole))])
                : __(':author mentioned you:', $params),
            '“'.$this->excerpt().'”',
        ];

        return $path === null ? [...$lines, __(self::NO_ACCESS_HINT)] : $lines;
    }

    /**
     * Spec 0381: the card of the annotated record. A note on a request that
     * names its Offerta is about that Offerta, not the whole Opportunity.
     *
     * @return array<string, string>
     */
    private function details(): array
    {
        $quote = $this->note->quote_id === null ? null : $this->note->loadMissing('quote')->quote;

        return RecordDetails::for($quote ?? $this->record);
    }

    /**
     * Resolved here, not by the caller: one notification instance is sent to
     * many recipients (Notification::send), and which screen shows this note
     * depends on the recipient's own abilities.
     */
    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return app(NoteEntityRegistry::class)
            ->deepLinkFor($this->entityType, $this->record, $notifiable, $this->note->quote_id);
    }

    private function message(?string $path, object $notifiable): string
    {
        $params = ['author' => $this->author->name, 'label' => $this->recordLabel, 'excerpt' => $this->excerpt()];

        $message = $this->wasEnlisted($notifiable)
            ? __(':author mentioned you in :label and added you as :role: :excerpt', $params + ['role' => __(ucfirst((string) $this->enlistedRole))])
            : __(':author mentioned you in :label: :excerpt', $params);

        if ($path !== null) {
            return $message;
        }

        return $message.' '.__(self::NO_ACCESS_HINT);
    }

    private function excerpt(): string
    {
        $namesById = $this->note->mentionedUsers->pluck('name', 'id')->all();

        return MentionParser::excerptWithNames($this->note->body, $namesById, self::EXCERPT_LENGTH);
    }

    private function wasEnlisted(object $notifiable): bool
    {
        return $this->enlistedRole !== null && in_array($notifiable->id, $this->enlistedUserIds, true);
    }
}
