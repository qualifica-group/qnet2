<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderPage;

use App\Http\Requests\WorkOrderChat\WorkOrderChatRequest;

/**
 * PUT of the notes page (spec 0420). Shares the chat requests' deferred
 * validation (WorkOrderChatRequest): authorization first, then the payload.
 * The size limits apply to the sanitized HTML, so they live in the service.
 */
class SaveWorkOrderPageRequest extends WorkOrderChatRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['present', 'nullable', 'string'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }
}
