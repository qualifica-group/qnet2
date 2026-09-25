<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Enums\TaskStatusGroup;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
use App\Models\Task;
use App\Services\Tasks\TaskTimeEntryRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks/{task}/complete (spec 0116,
 * data_contract; `time_entry` added by spec 0123, D-1). `closure_feedback`
 * and `validation_status_id` stay `sometimes|nullable` here: this
 * FormRequest cannot know the actor's mandate over the record, so it never
 * decides whether `validation_status_id` is required or forbidden — that
 * percorso-dependent rule lives in `TaskCompletionService::complete()` (spec
 * 0121, D-3). What DOES belong here is the shape any submitted value must
 * have regardless of percorso: an ACTIVE status belonging to the
 * `in_validation` group, in ONE combined rule — the same shape as
 * ValidateContractRequest's `contract_status_id`/closed_won pairing.
 *
 * `time_entry` is `required|array` on BOTH percorsi (D-1) UNLESS $task's
 * tipologia opts out of it (spec 0162, D-1/D-2, `TaskTimeEntryRequirement`):
 * then it drops to `sometimes|array`, and its `time_entry.*` sub-rules
 * (`TimeEntryValidationRules::rules('time_entry')` — the SAME source
 * `StoreTaskTimeEntryRequest` uses unprefixed, D-3/AC-009) only apply when
 * the client actually submitted the key, so an optional-time-entry task
 * completed without one raises no spurious nested error. A request with no
 * route-bound Task (e.g. AC-009's direct `rules()` call) defaults to
 * required, matching today's behaviour.
 *
 * Authorization stays in the controller (TaskPolicy::complete); D-2
 * deliberately does NOT check `time-entries.create` here or anywhere else in
 * this class.
 *
 * `for_all_assignees` (spec 0155, D-6) defaults false: `TaskCompletionService::complete()`
 * then logs the one submitted `time_entry` for the acting user alone, same as
 * before this spec. `true` logs an IDENTICAL copy of it for every assignee
 * (the actor themselves when there are none) — never a per-user choice, the
 * caller decides which value to send (list/detail always send `true`,
 * sub-task panel/kanban always `false`, q-net's own split).
 */
class CompleteTaskRequest extends FormRequest
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
        $task = $this->route('task');
        $required = ! $task instanceof Task || TaskTimeEntryRequirement::isRequired($task);

        $rules = [
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'validation_status_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('task_statuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->where('group', TaskStatusGroup::InValidation->value)
                ),
            ],
            'time_entry' => $required ? ['required', 'array'] : ['sometimes', 'array'],
            'for_all_assignees' => ['sometimes', 'boolean'],
        ];

        if (! $required && ! $this->has('time_entry')) {
            return $rules;
        }

        return array_merge($rules, TimeEntryValidationRules::rules('time_entry'));
    }

    /**
     * $taskId is injected from the route (never from the payload): it feeds
     * `TimeEntryData::forTask()`, the same derivation the task-scoped
     * segnatempo POST uses.
     */
    public function toData(int $taskId): CompleteTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CompleteTaskData::fromValidated($validated, $taskId);
    }
}
