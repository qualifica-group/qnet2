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
 * ONE summary of the Tasks a single operation closed (spec 0428): a bulk
 * Completa, or a parent closing its open sub-tasks with it. Sent in place of
 * the per-task TaskClosed/TaskFeedbackInserted to a recipient who would have
 * received two or more of them. Same shape as WorkOrderTasksAssignedNotification:
 * every fact precomputed by the caller (TaskClosureSummarySender), `action_url`
 * the commessa when every Task belongs to it and the recipient can open it.
 */
class TasksClosedSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable, QueuesMailSeparately, RespectsEmailPreference;

    /** The email lists at most this many titles, then says how many more. */
    private const int MAX_LISTED_TITLES = 30;

    /**
     * @param  array<int, string>  $taskTitles
     * @param  ?string  $actorName  null for a write nobody performed
     * @param  array<string, string>  $workOrderDetails  the commessa's card (RecordDetails)
     */
    public function __construct(
        private readonly array $taskTitles,
        private readonly ?string $actorName,
        private readonly ?int $workOrderId = null,
        private readonly ?string $workOrderLabel = null,
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
            [$this->message(), ...$this->titleLines()],
            $this->workOrderDetails,
            $this->pathFor($notifiable),
        );
    }

    private function pathFor(object $notifiable): ?string
    {
        if ($this->workOrderId === null) {
            return null;
        }

        /** @var User $notifiable */
        return RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::WorkOrder, $this->workOrderId);
    }

    private function title(): string
    {
        return __('Tasks completed');
    }

    private function message(): string
    {
        $replace = [
            'actor' => $this->actorName ?? __('The system'),
            'count' => count($this->taskTitles),
            'label' => $this->workOrderLabel,
        ];

        return $this->workOrderLabel === null
            ? __(':actor completed :count tasks.', $replace)
            : __(':actor completed :count tasks of the work order :label.', $replace);
    }

    /**
     * @return array<int, string>
     */
    private function titleLines(): array
    {
        $lines = array_map(fn (string $title): string => "- {$title}", array_slice($this->taskTitles, 0, self::MAX_LISTED_TITLES));
        $hidden = count($this->taskTitles) - self::MAX_LISTED_TITLES;

        if ($hidden > 0) {
            $lines[] = __('...and :count more.', ['count' => $hidden]);
        }

        return $lines;
    }
}
