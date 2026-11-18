<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Enums\TeamActivityEventEnum;
use App\Enums\TeamActivityRecordEnum;
use App\Models\User;
use App\Notifications\Concerns\QueuesMailSeparately;
use App\Notifications\Concerns\RespectsEmailPreference;
use App\Support\Notifications\RecordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the org-chart managers of whoever created or moved an Offerta, a
 * Contratto or a Commessa (spec 0413), modelled on
 * RecordAssignmentNotification: bell always, mail per preference, every fact
 * precomputed by TeamActivityNotifier but the link, which depends on the
 * recipient's own permissions.
 */
class TeamActivityNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    private const string NO_STATUS = '-';

    /**
     * @param  array<string, string>  $details  the record's detail card (a
     *                                          Contratto shows its Offerta's)
     */
    public function __construct(
        private readonly TeamActivityRecordEnum $record,
        private readonly TeamActivityEventEnum $event,
        private readonly int $recordId,
        private readonly string $recordLabel,
        private readonly string $actorName,
        private readonly ?string $fromStatus,
        private readonly ?string $toStatus,
        private readonly array $details = [],
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
            title: $this->title(),
            message: $this->message($path),
            level: NotificationLevelEnum::Info,
            actionUrl: $path,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        return RecordMail::compose(
            (new MailMessage)
                ->subject($this->title())
                ->greeting(__('Hello :name,', ['name' => $notifiable->name])),
            $this->message($path),
            $this->details,
            $path,
        );
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return $this->record->pathFor($notifiable, $this->recordId);
    }

    private function title(): string
    {
        return match ([$this->record, $this->event]) {
            [TeamActivityRecordEnum::Quote, TeamActivityEventEnum::Created] => __('Your team created a quote'),
            [TeamActivityRecordEnum::Quote, TeamActivityEventEnum::StatusChanged] => __('A quote of your team changed status'),
            [TeamActivityRecordEnum::Contract, TeamActivityEventEnum::Created] => __('Your team opened a contract'),
            [TeamActivityRecordEnum::Contract, TeamActivityEventEnum::StatusChanged] => __('A contract of your team changed status'),
            [TeamActivityRecordEnum::WorkOrder, TeamActivityEventEnum::Created] => __('Your team created a work order'),
            [TeamActivityRecordEnum::WorkOrder, TeamActivityEventEnum::StatusChanged] => __('A work order of your team changed status'),
        };
    }

    private function message(?string $path): string
    {
        $replace = [
            'actor' => $this->actorName,
            'label' => $this->recordLabel,
            'from' => $this->fromStatus ?? self::NO_STATUS,
            'to' => $this->toStatus ?? self::NO_STATUS,
        ];

        $message = match ([$this->record, $this->event]) {
            [TeamActivityRecordEnum::Quote, TeamActivityEventEnum::Created] => __(':actor created the quote :label.', $replace),
            [TeamActivityRecordEnum::Quote, TeamActivityEventEnum::StatusChanged] => __(':actor moved the quote :label from :from to :to.', $replace),
            [TeamActivityRecordEnum::Contract, TeamActivityEventEnum::Created] => __(':actor opened the contract of the quote :label.', $replace),
            [TeamActivityRecordEnum::Contract, TeamActivityEventEnum::StatusChanged] => __(':actor moved the contract of the quote :label from :from to :to.', $replace),
            [TeamActivityRecordEnum::WorkOrder, TeamActivityEventEnum::Created] => __(':actor created the work order :label.', $replace),
            [TeamActivityRecordEnum::WorkOrder, TeamActivityEventEnum::StatusChanged] => __(':actor moved the work order :label from :from to :to.', $replace),
        };

        if ($path !== null) {
            return $message;
        }

        return $message.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
