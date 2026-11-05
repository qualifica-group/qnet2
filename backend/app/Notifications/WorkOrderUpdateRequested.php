<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\AssignmentTargetEnum;
use App\Enums\NotificationLevelEnum;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Notifications\RecordDetails;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to the group "Richiedi aggiornamento" resolves on a work order (spec
 * 0352, clone of TaskUpdateRequested). `$isCc` marks the copy group, sent as a
 * SEPARATE notification so the stored `data.is_cc` tells the two apart.
 *
 * `action_url` is an internal PATH resolved PER RECIPIENT: someone who cannot
 * open work orders gets `null` and no mail button, never a link to a 403.
 */
class WorkOrderUpdateRequested extends Notification implements ShouldQueue
{
    use Queueable;

    private const int MESSAGE_LIMIT = 2000;

    public function __construct(
        private readonly WorkOrder $workOrder,
        private readonly User $requester,
        private readonly string $message,
        private readonly bool $isCc = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null, is_cc: bool}
     */
    public function toArray(object $notifiable): array
    {
        $path = $this->pathFor($notifiable);

        return [
            ...(new NotificationData(
                title: __('Update requested'),
                message: $this->body($path),
                level: NotificationLevelEnum::Info,
                actionUrl: $path,
            ))->toArray(),
            'is_cc' => $this->isCc,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = $this->pathFor($notifiable);

        $mail = (new MailMessage)
            ->subject(__('Update requested'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->body($path));

        if ($path !== null) {
            $mail->action(__('Open'), rtrim((string) config('app.frontend_url'), '/').$path);
        }

        return $mail;
    }

    private function pathFor(object $notifiable): ?string
    {
        /** @var User $notifiable */
        return RecordLinkResolver::pathFor($notifiable, AssignmentTargetEnum::WorkOrder, $this->workOrder->id);
    }

    private function body(?string $path): string
    {
        $body = __(':requester asked for an update on the work order :label', [
            'requester' => $this->requester->name,
            'label' => RecordDetails::labelFor($this->workOrder),
        ]).' '.Str::limit($this->message, self::MESSAGE_LIMIT);

        if ($path !== null) {
            return $body;
        }

        return $body.' '.__('You cannot open this record: ask an administrator to grant you access to the module.');
    }
}
