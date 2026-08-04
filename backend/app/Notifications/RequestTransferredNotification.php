<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\AssignmentTargetEnum;
use App\Enums\NotificationLevelEnum;
use App\Enums\TransferRecipientRoleEnum;
use App\Models\User;
use App\Support\Notifications\DetailsTable;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent on a "Trasferisci contatto" (spec 0079) to THREE disjoint audiences,
 * each with its own text (spec 0081): the operator who lost the contact, the
 * one who gained it, and everyone holding
 * `request-management.receiveTransferNotifications`. The recipient sets and
 * the exclusion of the actor are built by the caller
 * (RequestTransferService::dispatchNotifications()); `$recipientRole` only
 * selects which of the three stories this copy tells.
 *
 * `via(): ['database', 'mail']`, `ShouldQueue`, payload through the shared
 * NotificationData shape (title/message/level/action_url), `action_url` a
 * PATH INTERNO, never absolute — the mail CTA is the only place
 * `config('app.frontend_url')` is prepended.
 *
 * Every fact is precomputed by the caller and passed to the constructor: a
 * Notification stays a thin presentation of already-known facts, never a
 * second place that queries the database. The ONE exception is the link,
 * which depends on the RECIPIENT's own permissions and so is resolved per
 * notifiable (spec 0081): a transfer notifies people who reach the record
 * from the opportunities module and people who only reach it from request
 * management.
 */
class RequestTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $requestId,
        private readonly string $contactLabel,
        private readonly ?string $originSiteLabel,
        private readonly string $destinationSiteLabel,
        private readonly ?string $previousOperatorName,
        private readonly string $newOperatorName,
        private readonly string $actorName,
        private readonly Carbon $transferredAt,
        private readonly TransferRecipientRoleEnum $recipientRole,
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

        // A SHORT lead here, not the full sentence the bell shows: the detail
        // card right below carries the same facts as a table, and printing
        // them twice in one email reads as a mistake.
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->withAccessNote($this->lead(), $path))
            ->line(DetailsTable::markdown($this->details()));

        // No reachable module, no button: a CTA that lands on a 403 is worse
        // than none, and the message already says what to ask for.
        if ($path !== null) {
            $mail->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    /**
     * A path only (never an absolute URL, contract-frozen), and which path
     * depends on what THIS recipient may open (spec 0081).
     */
    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::Opportunity, $this->requestId);
    }

    private function title(): string
    {
        return match ($this->recipientRole) {
            TransferRecipientRoleEnum::PreviousOperator => __('Contact no longer assigned to you'),
            TransferRecipientRoleEnum::NewOperator => __('New contact assigned to you'),
            TransferRecipientRoleEnum::Supervisor => __('Contact transferred'),
        };
    }

    /**
     * The self-contained sentence the bell shows: it has no detail card to
     * lean on, so it spells every fact out.
     */
    private function message(?string $path): string
    {
        return $this->withAccessNote(match ($this->recipientRole) {
            TransferRecipientRoleEnum::PreviousOperator => __(':contact was transferred from :origin to :destination by :actor on :date. You are no longer the operator of this contact: it is now assigned to :new.', $this->placeholders()),
            TransferRecipientRoleEnum::NewOperator => __(':actor assigned you :contact, transferred from :origin to :destination on :date.', $this->placeholders()),
            TransferRecipientRoleEnum::Supervisor => __(':actor transferred :contact from :origin to :destination (operator :previous to :new) on :date', $this->placeholders()),
        }, $path);
    }

    /**
     * The email's opening line, deliberately short (see toMail()).
     */
    private function lead(): string
    {
        return match ($this->recipientRole) {
            TransferRecipientRoleEnum::PreviousOperator => __('You are no longer the operator of :contact.', $this->placeholders()),
            TransferRecipientRoleEnum::NewOperator => __(':actor assigned you the contact :contact.', $this->placeholders()),
            TransferRecipientRoleEnum::Supervisor => __(':actor transferred the contact :contact.', $this->placeholders()),
        };
    }

    /**
     * The detail card: every fact of the transfer, already in hand.
     *
     * @return array<string, string>
     */
    private function details(): array
    {
        return [
            'notifications.fields.contact' => $this->contactLabel,
            'notifications.fields.origin_site' => $this->originSiteLabel ?? __('notifications.values.empty'),
            'notifications.fields.destination_site' => $this->destinationSiteLabel,
            'notifications.fields.previous_operator' => $this->previousOperatorName ?? __('notifications.values.empty'),
            'notifications.fields.new_operator' => $this->newOperatorName,
            'notifications.fields.performed_by' => $this->actorName,
            'notifications.fields.date' => $this->transferredAt->format('d/m/Y H:i'),
        ];
    }

    private function withAccessNote(string $message, ?string $path): string
    {
        if ($path !== null) {
            return $message;
        }

        return $message.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        return [
            'actor' => $this->actorName,
            'contact' => $this->contactLabel,
            'origin' => $this->originSiteLabel ?? '—',
            'destination' => $this->destinationSiteLabel,
            'previous' => $this->previousOperatorName ?? '—',
            'new' => $this->newOperatorName,
            'date' => $this->transferredAt->format('d/m/Y H:i'),
        ];
    }
}
