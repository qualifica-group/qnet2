<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderChat;

use Illuminate\Validation\Rule;

/**
 * POST /api/work-orders/{workOrder}/chat/read (spec 0354): the message must
 * belong to this work order (a soft deleted one still counts as a position).
 */
class MarkWorkOrderChatReadRequest extends WorkOrderChatRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'last_message_id' => [
                'required',
                'integer',
                Rule::exists('work_order_chat_messages', 'id')->where('work_order_id', $this->workOrder()->id),
            ],
        ];
    }
}
