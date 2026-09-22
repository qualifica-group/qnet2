<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderStages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/stages (spec
 * 0146, data_contract). Authorization is intentionally NOT handled here (it
 * stays in the controller via authorize('update', $workOrder) — a stage
 * mutation is gated on the OWNING commessa, D-9, not on a Policy of its own).
 */
class StoreWorkOrderStageRequest extends FormRequest
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
