<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskNotable;
use App\Support\Notifications\DetailsTable;
use App\Support\Notifications\TaskDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The shared half of the eleven Task notifications of the product document
 * (spec 0119, D-1). Channels, payload shape, per-recipient deep link and mail
 * assembly are identical across all eleven; only the title, the sentence and
 * the severity differ, so those three are what a subclass declares.
 *
 * Modelled on TaskUpdateRequested (spec 0118), which stays a standalone class:
 * it is not part of the document's map, it carries a free-text message the
 * others have no notion of, and rewriting it onto this base would churn a
 * green feature for symmetry alone.
 *
 * A subclass is a LEAF: the notification class name is persisted in the
 * `type` column of `notifications`, so it is data, and the hierarchy must stay
 * one level deep for that column to keep meaning what it says.
 *
 * Every subclass takes the same two constructor arguments — the Task and the
 * actor who caused the event — and nothing else: a Notification presents facts
 * the caller already holds and never queries for more (AC-005). The ONE thing
 * it cannot precompute is the deep link, which depends on the RECIPIENT's own
 * abilities, hence the per-notifiable resolution below.
 */
abstract class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  ?User  $actor  who performed the write; null for a
     *                        system-initiated one (a generated recurrence,
     *                        an import), in which case the body names the
     *                        system as the author.
     */
    public function __construct(
        protected readonly Task $task,
        protected readonly ?User $actor,
    ) {}

    /**
     * @return array<int, string>
     */
    final public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    final public function toArray(object $notifiable): array
    {
        $path = $this->pathFor($notifiable);

        return (new NotificationData(
            title: $this->title(),
            message: $this->message($path),
            level: $this->level(),
            actionUrl: $path,
        ))->toArray();
    }

    final public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message($path));

        // D-8: the detail card, rendered as a markdown table. Null when the
        // Task carries nothing worth tabulating, in which case the block is
        // omitted rather than printed empty.
        $details = DetailsTable::markdown(TaskDetails::for($this->task));

        if ($details !== null) {
            $mail->line($details);
        }

        // No reachable screen, no button: a CTA that lands on a 403 is worse
        // than none (D-7).
        if ($path !== null) {
            $mail->action(__('Open'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    /** The subject line and the bell's title. */
    abstract protected function title(): string;

    /**
     * The sentence. It MUST name the Task's title (AC-032) so the bell reads
     * without opening the record, and it is built here — not in the
     * constructor — because `__()` has to run inside the recipient's locale.
     */
    abstract protected function body(): string;

    /** D-11: severity drives the bell's colour, never behaviour. */
    protected function level(): NotificationLevelEnum
    {
        return NotificationLevelEnum::Info;
    }

    /** The actor's name, or the system for a write nobody performed. */
    final protected function actorName(): string
    {
        return $this->actor?->name ?? __('The system');
    }

    /**
     * The Task's title, quoted, ready to drop into a sentence.
     */
    final protected function taskTitle(): string
    {
        return $this->task->title;
    }

    /**
     * Resolved here and not by the caller: which screen this Task shows — or
     * whether it shows one at all — depends on the recipient's own abilities,
     * and every event below reaches several recipients at once.
     */
    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return app(TaskNotable::class)->deepLinkPath($this->task, $notifiable, null);
    }

    /**
     * The body, plus the one line that tells a recipient outside the Task's
     * visibility scope why they got no button (mirrors TaskUpdateRequested).
     */
    private function message(?string $path): string
    {
        if ($path !== null) {
            return $this->body();
        }

        return $this->body().' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
