<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderStages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PATCH /api/work-orders/{workOrder}/stages/{stage}
 * (spec 0146, data_contract): the same shape as the store request — a rename
 * is the only mutation this endpoint carries. Authorization is intentionally
 * NOT handled here (it stays in the controller via authorize('update',
 * $workOrder)).
 */
class UpdateWorkOrderStageRequest extends FormRequest
{
    private const int NAME_MAX = 191;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
        ];
    }

    public function name(): string
    {
        return (string) $this->validated('name');
    }
}
