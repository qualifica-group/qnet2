<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderChatMessage;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderChatMessage
 *
 * One chat message (spec 0359 ChatMessage), identical in the REST responses
 * and in the broadcast payload, so it carries nothing that depends on the
 * reader. Needs `author.avatar` eager-loaded.
 */
class WorkOrderChatMessageResource extends JsonResource
{
    private bool $withAvatar = true;

    /**
     * The broadcast variant: Reverb rejects HTTP publishes over max_request_size
     * (10000 bytes by default), so the base64 avatar stays out and the frontend
     * keeps it from its cache by `author.id`.
     */
    public function withoutAvatar(): static
    {
        $this->withAvatar = false;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deleted = $this->trashed();
        $author = $this->author;

        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'author' => $author === null ? null : [
                'id' => $author->id,
                'name' => $author->name,
                'avatar_url' => $this->withAvatar ? $author->avatarDataUri() : null,
            ],
            'body' => $deleted ? null : $this->body,
            'is_deleted' => $deleted,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'editable_until' => app(WorkOrderChatAccess::class)->editableUntil($this->resource)->toIso8601String(),
        ];
    }
}
