<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OutboundEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row projection for `GET /api/work-orders/{workOrder}/emails` (spec 0175
 * frozen `data_contract`, type `OutboundEmailListItem`). `attachments_count`
 * requires the caller to have added it via `withCount` (OutboundEmailService)
 * scoped to `OutboundEmail::ATTACHMENT_COLLECTION` and the `recurrence`
 * relation (spec 0362) -- this resource never queries.
 *
 * @mixin OutboundEmail
 */
class OutboundEmailListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'to' => $this->to_recipients ?? [],
            'subject' => $this->subject,
            'attachments_count' => (int) $this->attachments_count,
            'next_send_on' => $this->recurrence?->next_send_on?->toDateString(),
            'is_recurring_copy' => $this->outbound_email_recurrence_id !== null,
            'sent_at' => $this->sent_at,
            'failed_at' => $this->failed_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
