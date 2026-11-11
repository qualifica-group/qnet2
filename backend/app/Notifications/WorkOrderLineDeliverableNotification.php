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
 * Sent to the supervisors and participants of a commessa when the payment
 * status of one of its lines turns into one that "si puo' consegnare" (spec
 * 0201, D-4/D-13). Same shape as RecordAssignmentNotification: `database` +
 * `mail`, queued, payload through NotificationData, `action_url` a path the
 * recipient's own permissions decide (null when they cannot open the commessa).
 * Every fact is precomputed by the caller (WorkOrderLinePaymentWriter).
 */
class WorkOrderLineDeliverableNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsEmailPreference;

    /**
     * @param  array<string, string>  $workOrderDetails  the commessa's card
     *                                                   (RecordDetails), built
     *                                                   by the caller
     */
    public function __construct(
        private readonly int $workOrderId,
        private readonly string $workOrderLabel,
        private readonly string $productName,
        private readonly string $statusName,
        private readonly string $actorName,
        private readonly array $workOrderDetails = [],
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
            level: NotificationLevelEnum::Info,
            actionUrl: $this->pathFor($notifiable),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Spec 0381: the line first, then the commessa it belongs to.
        return RecordMail::compose(
            (new MailMessage)
                ->subject($this->title())
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->message(),
            [
                'notifications.fields.product' => $this->productName,
                'notifications.fields.payment_status' => $this->statusName,
                ...$this->workOrderDetails,
            ],
            $this->pathFor($notifiable),
        );
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::WorkOrder, $this->workOrderId);
    }

    private function title(): string
    {
        return __('Line ready for delivery');
    }

    private function message(): string
    {
        return __(':actor set the payment status of ":product" on :label to ":status": it can be delivered.', [
            'actor' => $this->actorName,
            'product' => $this->productName,
            'label' => $this->workOrderLabel,
            'status' => $this->statusName,
        ]);
    }
}
