<?php

namespace App\Models;

use App\Enums\ArchivioSmartWebhookEventStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One inbound ArchivioSmart webhook event (spec 0428, D-6). The unique `event_id`
 * is the idempotency key; the body is never stored.
 */
#[Fillable(['event_id', 'event', 'document_request_id', 'occurred_at', 'status', 'processed_at'])]
class ArchivioSmartWebhookEvent extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'status' => ArchivioSmartWebhookEventStatus::class,
            'processed_at' => 'datetime',
        ];
    }
}
