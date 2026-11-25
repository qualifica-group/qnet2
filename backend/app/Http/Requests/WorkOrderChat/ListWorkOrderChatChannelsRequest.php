<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/work-order-chat/channels (spec 0432): no input. Any authenticated
 * user may ask; the list itself is scoped to the actor's team work orders and
 * empty without the viewing permission (`WorkOrderChatChannels`).
 */
class ListWorkOrderChatChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
