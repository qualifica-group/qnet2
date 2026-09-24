<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\BulkTaskData;
use App\Enums\TaskStatusGroup;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks/bulk (spec 0156, D-6/contract).
 * One request carries NINE possible actions, each with its own required
 * companion field — `rules()` reads the submitted `action` to decide which
 * companion is `required_if`/`prohibited_unless`, the same shape
 * `App\Http\Requests\TaskBoard\BulkTaskBoardRequest` already uses.
 *
 * `task_ids.*` carries no `exists:tasks,id` rule on purpose (unlike its
 * board twin): whether an id exists at all, or exists but is not VISIBLE to
 * the actor, are both "incompatible" outcomes of the SAME contract shape
 * (`incompatible_tasks`) — `App\Services\Tasks\TaskBulkService` answers both
 * uniformly, never a structural 422 that would leak which case applied.
 *
 * `time_entry.*` is the SAME source `CompleteTaskRequest`/
 * `BulkTaskBoardRequest` use (`TimeEntryValidationRules::rules()`), merged
 * only when `action=complete`.
 *
 * Authorization is intentionally NOT handled here: the RESOURCE-level base
 * permission of the chosen action (contract: "403 senza il permesso base
 * dell'azione") is checked in the controller, and the PER-TASK ability is
 * re-asserted by TaskBulkActionExecutor, which turns a per-row 403 into an
 * `incompatible_tasks` entry rather than aborting the whole request.
 */
class BulkTaskRequest extends FormRequest
{
    private const int MAX_TASK_IDS = 200;

    /**
     * @var array<int, string>
     */
    private const array ACTIONS = ['assign', 'complete', 'uncomplete', 'block', 'unblock', 'priority', 'start_date', 'end_date', 'delete'];

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
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'task_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_TASK_IDS],
            'task_ids.*' => ['integer', 'distinct'],

            'assignee_ids' => ['required_if:action,assign', 'prohibited_unless:action,assign', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],

            'task_priority_id' => [
                'required_if:action,priority',
                'prohibited_unless:action,priority',
                'integer',
                Rule::exists('task_priorities', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],

            'date' => ['required_if:action,start_date', 'required_if:action,end_date', 'prohibited_unless:action,start_date,end_date', 'date'],

            'closure_feedback' => ['sometimes', 'nullable', 'string', 'prohibited_unless:action,complete'],
            'validation_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                'prohibited_unless:action,complete',
                Rule::exists('task_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('group', TaskStatusGroup::InValidation->value)
                ),
            ],
        ];

        if ($this->input('action') === 'complete') {
            $rules['time_entry'] = ['required', 'array'];

            return array_merge($rules, TimeEntryValidationRules::rules('time_entry'));
        }

        $rules['time_entry'] = ['prohibited'];

        return $rules;
    }

    public function toData(): BulkTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return BulkTaskData::fromValidated($validated);
    }
}
