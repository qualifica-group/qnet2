<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderChat;

use App\Services\WorkOrders\Chat\WorkOrderChatService;

/**
 * GET /api/work-orders/{workOrder}/chat/messages (spec 0354).
 */
class ListWorkOrderChatMessagesRequest extends WorkOrderChatRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'before_id' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.WorkOrderChatService::MAX_LIMIT],
        ];
    }

    public function beforeId(): ?int
    {
        return $this->has('before_id') ? (int) $this->validated('before_id') : null;
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? WorkOrderChatService::DEFAULT_LIMIT);
    }
}
