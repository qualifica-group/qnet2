<?php

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\Note;
use App\Models\User;
use App\Notes\Mentions\MentionParser;
use App\Notes\NoteEntityRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
    use Queueable;

    private const int EXCERPT_LENGTH = 140;

    /**
     * @param  string  $entityType  the `notable_types` slug of $record, the
     *                              only thing the link resolution needs on
     *                              top of the recipient's own abilities
     * @param  Model  $record  the note's host record, passed in rather than
     *                         read back off `$note->notable`: a Notification
     *                         presents facts the caller already holds, it
     *                         does not query for them
     */
    public function __construct(
        private readonly Note $note,
        private readonly User $author,
        private readonly string $recordLabel,
        private readonly string $entityType,
        private readonly Model $record,
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
            title: __('You were mentioned'),
            message: $this->message($path),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        $mail = (new MailMessage)
            ->subject(__('You were mentioned'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message($path));

        // No reachable screen, no button: a CTA that lands on a 403 is worse
        // than none, and the message already says what to ask for.
        if ($path !== null) {
            $mail->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
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

    private function message(?string $path): string
    {
        $namesById = $this->note->mentionedUsers->pluck('name', 'id')->all();
        $resolvedBody = MentionParser::resolveTokens($this->note->body, $namesById);
        $excerpt = Str::limit($resolvedBody, self::EXCERPT_LENGTH);

        $message = __(':author mentioned you in :label: :excerpt', [
            'author' => $this->author->name,
            'label' => $this->recordLabel,
            'excerpt' => $excerpt,
        ]);

        if ($path !== null) {
            return $message;
        }

        return $message.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
