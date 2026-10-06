<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OutboundEmailStatus;
use App\Models\Attachment;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single OutboundEmail as returned by GET/POST/PATCH
 * `/api/work-orders/{workOrder}/emails[/{email}]` (spec 0175 frozen
 * `data_contract`, type `OutboundEmail`). `attachments` requires the
 * relation ALREADY eager-loaded and pre-filtered to
 * `OutboundEmail::ATTACHMENT_COLLECTION` by the caller (OutboundEmailService)
 * -- this resource never queries.
 *
 * `can` mirrors the per-instance authorization BE-05's own visibility/
 * ownership rules already resolved (D-2/D-3/D-6), so the composer gates its
 * own buttons on it instead of re-deriving the rules client-side.
 *
 * @mixin OutboundEmail
 */
class OutboundEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $actor */
        $actor = $request->user();
        $isOwnDraft = $this->status === OutboundEmailStatus::Draft && $actor !== null && $this->sender_user_id === $actor->id;
        $isOwnFailed = $this->status === OutboundEmailStatus::Failed && $actor !== null && $this->sender_user_id === $actor->id;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'purpose' => $this->purpose?->value,
            'email_template_id' => $this->email_template_id,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ],
            'from_address' => $this->from_address,
            'to' => $this->to_recipients ?? [],
            'cc' => $this->cc_recipients ?? [],
            'bcc' => $this->bcc_recipients ?? [],
            'subject' => $this->subject,
            'body' => $this->body,
            'attachments' => $this->attachmentsData(),
            'attachments_total_size' => $this->attachments->sum('size'),
            'queued_at' => $this->queued_at,
            'sent_at' => $this->sent_at,
            'failed_at' => $this->failed_at,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'can' => [
                'update' => $isOwnDraft,
                'delete' => $isOwnDraft,
                'send' => $isOwnDraft || $isOwnFailed,
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, original_name: string, mime_type: string, size: int}>
     */
    private function attachmentsData(): array
    {
        /** @var Collection<int, Attachment> $attachments */
        $attachments = $this->attachments;

        return $attachments->map(fn ($attachment): array => [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
        ])->all();
    }
}
