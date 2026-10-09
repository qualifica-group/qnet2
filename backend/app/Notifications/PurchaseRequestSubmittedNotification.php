<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use App\Support\Notifications\DetailsTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the function manager of a purchase request (spec 0208, D-2): when the
 * request is created and whenever the "Invia al responsabile" button is used.
 * Same shape as RecordAssignmentNotification: database + mail, queued, the
 * facts precomputed by PurchaseRequestNotifier, and an internal PATH as
 * `action_url` (resolved per recipient: no link when they cannot open it).
 */
class PurchaseRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $details  english label => value, shown in the email
     */
    public function __construct(
        private readonly int $purchaseRequestId,
        private readonly string $subjectLine,
        private readonly string $actorName,
        private readonly array $details = [],
    ) {}

    /**
     * The mail channel is skipped when the recipient has no address.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationData(
            title: $this->title(),
            message: $this->message(),
            level: NotificationLevelEnum::Info,
            actionUrl: $this->pathFor($notifiable),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message());

        $table = DetailsTable::markdown($this->details);

        if ($table !== null) {
            $mail->line($table);
        }

        $path = $this->pathFor($notifiable);

        if ($path !== null) {
            $mail->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return $notifiable->can('purchase-requests.view') ? "/purchase-requests/{$this->purchaseRequestId}" : null;
    }

    private function title(): string
    {
        return __('Purchase request to review');
    }

    private function message(): string
    {
        return __(':actor sent you the purchase request ":subject" for review.', [
            'actor' => $this->actorName,
            'subject' => $this->subjectLine,
        ]);
    }
}
