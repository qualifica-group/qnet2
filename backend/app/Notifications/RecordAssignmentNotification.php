<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use App\Support\Notifications\DetailsTable;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a user who was just put in charge of a record (spec 0081): as
 * Supervisore or as "Gestore Account" of an anagrafica or of an
 * opportunita'/richiesta. ONE class for the four combinations rather than
 * four near-identical ones: target and role are data, not structure — they
 * only pick a sentence.
 *
 * Modelled on RequestTransferredNotification: `via(): ['database', 'mail']`,
 * `ShouldQueue`, payload through NotificationData (title/message/level/
 * action_url), `action_url` a PATH INTERNO never absolute.
 *
 * Every fact is precomputed by the caller (AssignmentNotifier) and passed to
 * the constructor: a Notification stays a thin presentation of already-known
 * facts, never a second place that queries the database. The ONE thing it
 * cannot precompute is the link, which depends on the RECIPIENT's own
 * permissions — hence the per-notifiable resolution below.
 */
class RecordAssignmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $details  the record's detail card,
     *                                          english label => value, built
     *                                          by RecordDetails before dispatch
     * @param  ?int  $requestManagementRecordId  spec 0086, MT-04b: overrides
     *                                           $recordId for the
     *                                           request-management branch of
     *                                           the deep link only (a grid
     *                                           row is a Quote, not an
     *                                           Opportunity, since spec
     *                                           0086) — null keeps the
     *                                           pre-0086 behaviour of
     *                                           reusing $recordId for both
     *                                           branches.
     */
    public function __construct(
        private readonly AssignmentTargetEnum $target,
        private readonly AssignmentRoleEnum $role,
        private readonly int $recordId,
        private readonly string $recordLabel,
        private readonly ?int $position,
        private readonly string $actorName,
        private readonly array $details = [],
        private readonly ?int $requestManagementRecordId = null,
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
            title: $this->title(),
            message: $this->message($path),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message($path));

        // The detail card rides on the EMAIL only: the bell shows a one-line
        // summary and the record is one click away there.
        $table = DetailsTable::markdown($this->details);

        if ($table !== null) {
            $mail->line($table);
        }

        // No reachable module, no button: a CTA that lands on a 403 is worse
        // than none, and the message already says what to ask for.
        if ($path !== null) {
            $mail->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return RecordLinkResolver::pathFor($notifiable, $this->target, $this->recordId, $this->requestManagementRecordId);
    }

    private function title(): string
    {
        return match ($this->role) {
            AssignmentRoleEnum::Supervisor => __('You were assigned as Supervisor'),
            AssignmentRoleEnum::Manager => __('You were assigned as Account Manager'),
        };
    }

    private function message(?string $path): string
    {
        $message = match ($this->role) {
            AssignmentRoleEnum::Supervisor => __(':actor assigned you as Supervisor on :label.', [
                'actor' => $this->actorName,
                'label' => $this->recordLabel,
            ]),
            AssignmentRoleEnum::Manager => __(':actor assigned you as Account Manager :position on :label.', [
                'actor' => $this->actorName,
                'position' => (string) $this->position,
                'label' => $this->recordLabel,
            ]),
        };

        if ($path !== null) {
            return $message;
        }

        return $message.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
