<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Sent to the newly-assigned GA2 "Operatore" plus every titolare of the
 * `supervisor` role (spec 0079, decision utente 2026-08-04), excluded the
 * actor who performed the transfer (recipient list/exclusion built by the
 * caller, RequestTransferService::recipients()). Modelled on
 * FieldChangeRequestedNotification: `via(): ['database', 'mail']`,
 * `ShouldQueue`, payload through the same NotificationData shape
 * (title/message/level/action_url), `action_url` a PATH INTERNO, never
 * absolute — the mail CTA is the only place `config('app.frontend_url')` is
 * prepended.
 *
 * Every fact is precomputed by the caller and passed to the constructor: a
 * Notification stays a thin presentation of already-known facts, never a
 * second place that queries the database.
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
        return (new NotificationData(
            title: __('Contact transferred'),
            message: $this->message(),
            level: NotificationLevelEnum::Info,
            actionUrl: $this->dataActionUrl(),
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Contact transferred'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message())
            ->action(__('View'), rtrim((string) config('app.frontend_url'), '/').$this->dataActionUrl());
    }

    /**
     * A path only (never an absolute URL, contract-frozen): see
     * FieldChangeRequestedNotification's own dataActionUrl().
     */
    private function dataActionUrl(): string
    {
        return "/request-management/{$this->requestId}";
    }

    private function message(): string
    {
        return __(':actor transferred :contact from :origin to :destination (operator :previous to :new) on :date', [
            'actor' => $this->actorName,
            'contact' => $this->contactLabel,
            'origin' => $this->originSiteLabel ?? '—',
            'destination' => $this->destinationSiteLabel,
            'previous' => $this->previousOperatorName ?? '—',
            'new' => $this->newOperatorName,
            'date' => $this->transferredAt->format('d/m/Y H:i'),
        ]);
    }
}
