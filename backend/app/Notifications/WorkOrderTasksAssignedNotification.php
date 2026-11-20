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
 * ONE summary sent to each supervisor of a just-created commessa for all the
 * tasks its Modello di Task generated and assigned to them, in place of one
 * TaskAssigned per generated task: a 21-row template used to land 21 bell
 * entries and 21 emails in a few seconds. Same shape as
 * WorkOrderLineDeliverableNotification: `database` + `mail`, queued, every
 * fact precomputed by the caller (GeneratedTasksNotifier), `action_url` the
 * commessa when the recipient can open it.
 */
class WorkOrderTasksAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    /**
     * @param  array<string, string>  $workOrderDetails  the commessa's card
     *                                                   (RecordDetails), built
     *                                                   by the caller
     */
    public function __construct(
        private readonly int $workOrderId,
        private readonly string $workOrderLabel,
        private readonly int $taskCount,
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
        return RecordMail::compose(
            (new MailMessage)
                ->subject($this->title())
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->message(),
            $this->workOrderDetails,
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
        return __('Work order tasks assigned to you');
    }

    private function message(): string
    {
        return trans_choice('{1} :actor assigned you the task of the work order :label.|[2,*] :actor assigned you the :count tasks of the work order :label.', $this->taskCount, [
            'actor' => $this->actorName,
            'label' => $this->workOrderLabel,
        ]);
    }
}
