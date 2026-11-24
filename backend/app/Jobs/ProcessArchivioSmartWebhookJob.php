<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ArchivioSmartWebhookEventStatus;
use App\Models\ArchivioSmartWebhookEvent;
use App\Services\DocumentRequests\ArchivioSmartWebhookApplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Applies one recorded ArchivioSmart webhook event (spec 0428, D-6). The event row
 * is the idempotency guard: an event already processed or ignored is skipped.
 */
class ProcessArchivioSmartWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $eventId,
        public readonly array $payload,
    ) {}

    public function handle(ArchivioSmartWebhookApplier $applier): void
    {
        $event = ArchivioSmartWebhookEvent::query()->find($this->eventId);
        if ($event === null || $event->status !== ArchivioSmartWebhookEventStatus::Received) {
            return;
        }

        $status = $applier->apply($event, $this->payload);

        $event->forceFill(['status' => $status, 'processed_at' => now()])->save();
    }
}
