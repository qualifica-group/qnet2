<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderChat;

use App\Services\WorkOrders\Chat\WorkOrderChatService;

/**
 * POST and PATCH of a chat message (spec 0354): a plain text body, trimmed by
 * the TrimStrings middleware (blank becomes null, so `required` rejects it).
 */
class WriteWorkOrderChatMessageRequest extends WorkOrderChatRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:'.WorkOrderChatService::MAX_BODY_LENGTH]];
    }
}
