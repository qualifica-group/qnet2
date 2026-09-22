<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskBoard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/task-board/move
 * (spec 0146, data_contract). `work_order_stage_id` is `present` rather than
 * `sometimes`: the contract's own `int|null` means the key is always part of
 * the payload, either an id or an explicit null for "Senza fase" — never
 * merely absent, which would leave the destination group ambiguous.
 *
 * Both existence rules are SHAPE-only (the row exists at all): whether the
 * task is a root of THIS commessa and the stage belongs to THIS commessa are
 * RESULTING-state questions `App\Services\WorkOrders\TaskStagePositioner`'s
 * caller answers instead (422, mirroring how `App\Services\TaskService`
 * keeps referent/registry coherence out of the FormRequest layer).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $task) — `TaskAbilityResolver::
 * canUpdate`, D-9).
 */
class MoveTaskBoardRequest extends FormRequest
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
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')],
            'work_order_stage_id' => ['present', 'nullable', 'integer', Rule::exists('work_order_stages', 'id')],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function taskId(): int
    {
        return (int) $this->validated('task_id');
    }

    public function workOrderStageId(): ?int
    {
        $value = $this->validated('work_order_stage_id');

        return $value === null ? null : (int) $value;
    }

    public function position(): int
    {
        return (int) $this->validated('position');
    }
}
