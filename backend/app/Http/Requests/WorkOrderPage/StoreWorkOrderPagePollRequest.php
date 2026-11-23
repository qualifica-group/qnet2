<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderPage;

use App\Http\Requests\WorkOrderChat\WorkOrderChatRequest;

/**
 * POST of a page poll (spec 0420, D-7): a question and 2..10 distinct options.
 */
class StoreWorkOrderPagePollRequest extends WorkOrderChatRequest
{
    public const int MIN_OPTIONS = 2;

    public const int MAX_OPTIONS = 10;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:'.self::MIN_OPTIONS, 'max:'.self::MAX_OPTIONS],
            'options.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
        ];
    }
}
