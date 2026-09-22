<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskBoard;

use App\DataObjects\WorkOrders\BulkTaskBoardData;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/work-orders/{workOrder}/task-board/bulk
 * (spec 0146, D-7/data_contract). One request carries SIX possible actions,
 * each with its own required companion field — `rules()` reads the submitted
 * `action` to decide which companion is `required_if`/`prohibited`, the same
 * shape `CompleteTaskRequest` already uses for its own percorso-dependent
 * `validation_status_id`.
 *
 * `time_entry.*` is the SAME source `CompleteTaskRequest`/
 * `StoreTaskTimeEntryRequest` use (`TimeEntryValidationRules::rules()`),
 * merged only when `action=complete` — every other action must not carry it
 * at all (`prohibited`).
 *
 * `block` carries no inner rules on purpose: `App\Http\Requests\Tasks\
 * BlockTaskRequest`, the single-task sibling this mirrors, has none either
 * (spec 0116 — block is bodyless).
 *
 * `task_ids.*` existence is validated here (shape only): whether each id is
 * actually a ROOT of THIS commessa is a resulting-state question
 * `App\Services\WorkOrders\TaskBulkActionService`'s caller answers instead
 * (422), the same split `MoveTaskBoardRequest` draws.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('view', $workOrder) — D-9: the per-task ability
 * is re-asserted per row by the Service, never a blanket gate here).
 */
class BulkTaskBoardRequest extends FormRequest
{
    private const int MAX_TASK_IDS = 200;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'action' => ['required', 'string', Rule::in(['assign', 'complete', 'uncomplete', 'block', 'priority', 'dates'])],
            'task_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_TASK_IDS],
            'task_ids.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')],

            'assignee_ids' => ['required_if:action,assign', 'prohibited_unless:action,assign', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],

            'closure_feedback' => ['sometimes', 'nullable', 'string', 'prohibited_unless:action,complete'],
            'block' => ['sometimes', 'array', 'prohibited_unless:action,block'],

            'task_priority_id' => [
                'required_if:action,priority',
                'prohibited_unless:action,priority',
                'integer',
                Rule::exists('task_priorities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],

            'start_date' => ['sometimes', 'nullable', 'date', 'prohibited_unless:action,dates'],
            'end_date' => ['sometimes', 'nullable', 'date', 'prohibited_unless:action,dates'],
        ];

        if ($this->input('action') === 'complete') {
            $rules['time_entry'] = ['required', 'array'];

            return array_merge($rules, TimeEntryValidationRules::rules('time_entry'));
        }

        $rules['time_entry'] = ['prohibited'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('action') !== 'dates') {
                return;
            }

            $hasStart = $this->filled('start_date');
            $hasEnd = $this->filled('end_date');

            if (! $hasStart && ! $hasEnd) {
                $validator->errors()->add('start_date', 'At least one of start_date or end_date is required.');

                return;
            }

            if ($hasStart && $hasEnd && $this->input('end_date') < $this->input('start_date')) {
                $validator->errors()->add('end_date', 'end_date must not be before start_date.');
            }
        });
    }

    public function toData(): BulkTaskBoardData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return BulkTaskBoardData::fromValidated($validated);
    }
}
