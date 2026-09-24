<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/tasks/{task}/subtasks/reorder (spec
 * 0155, D-4/D-5, contract): `ids` must be an exact permutation of $task's
 * own DIRECT children. `distinct` is the shape-level guard (no id submitted
 * twice); the SEMANTIC guard — the set must be exactly this Task's own
 * children, no foreign id, none missing — is
 * App\Services\Tasks\TaskSubtaskReorderService::reorder()'s job (a single
 * FormRequest rule cannot express a "set equality against the DB"
 * constraint), mirroring App\Http\Requests\WorkOrderStages\ReorderWorkOrderStagesRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $task)).
 */
class ReorderSubtasksRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function subtaskIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('ids');

        return array_map(intval(...), $ids);
    }
}
