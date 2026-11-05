<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderChat;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the chat requests (spec 0359). Validation is NOT run on resolution:
 * the controller authorizes first (403/404) and then calls validatePayload(),
 * so every error, 404 and 409 included, goes through the controller's envelope.
 */
abstract class WorkOrderChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /** Skips the automatic validation, see the class comment. */
    public function validateResolved(): void {}

    public function validatePayload(): void
    {
        parent::validateResolved();
    }

    protected function workOrder(): WorkOrder
    {
        /** @var WorkOrder */
        return $this->route('workOrder');
    }
}
