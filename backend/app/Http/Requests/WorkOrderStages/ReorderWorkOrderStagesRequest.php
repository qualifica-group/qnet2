<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderStages;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/stages/reorder
 * (spec 0146, data_contract): `stage_ids` must be an exact permutation of the
 * commessa's own stages. `distinct` is the shape-level guard (no duplicate id
 * submitted twice); the SEMANTIC guard — the set must be exactly this
 * commessa's stage ids, no foreign id, none missing —
 * `WorkOrderStageService::reorder()` owns instead (a single FormRequest rule
 * cannot express a "set equality against the DB" constraint), mirroring
 * `App\Http\Requests\Statuses\ReorderStatusesRequest`.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $workOrder)).
 */
class ReorderWorkOrderStagesRequest extends FormRequest
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
        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function stageIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('stage_ids');

        return array_map(intval(...), $ids);
    }
}
