<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Broadcasting\WorkOrderChatChannel;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Services\WorkOrders\Chat\WorkOrderChatChannels;
use App\Services\WorkOrders\WorkOrderReferences;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin WorkOrder
 *
 * One channel of the chat dock (spec 0432 data_contract), built from a row of
 * `WorkOrderChatChannels::forUser()`. The channel name comes from
 * `WorkOrderChatChannel`, the only place that generates it.
 */
class WorkOrderChatChannelResource extends JsonResource
{
    /** Characters of the last message shown in the list (D-4). */
    public const int PREVIEW_LENGTH = 120;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkOrderChatMessage|null $last */
        $last = $this->resource->getRelation('lastChatMessage');

        return [
            'work_order_id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'registry_name' => app(WorkOrderReferences::class)->registry($this->resource)?->name,
            'unread_count' => (int) ($this->getAttribute(WorkOrderChatChannels::UNREAD_COLUMN) ?? 0),
            // Same rule as WorkOrderChatAccess::canWrite for a team member: the work still ongoing.
            'can_write' => $this->work_phase->isOngoing(),
            'channel' => WorkOrderChatChannel::name($this->resource),
            'last_message' => $last === null ? null : [
                'author_name' => $last->author?->name,
                'body_preview' => $last->trashed() ? null : Str::limit((string) $last->body, self::PREVIEW_LENGTH),
                'is_deleted' => $last->trashed(),
                'created_at' => $last->created_at?->toIso8601String(),
            ],
        ];
    }
}
