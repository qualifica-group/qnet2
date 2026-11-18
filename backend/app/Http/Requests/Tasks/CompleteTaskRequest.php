<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Enums\TaskStatusGroup;
use App\Http\Requests\TimeEntries\BulkTimeEntrySharesRules;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
use App\Models\Task;
use App\Services\Tasks\TaskActionAvailability;
use App\Services\Tasks\TaskTimeEntryRequirement;
use Illuminate\Contracts\Validation\Validator;
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
 * `for_all_assignees` (spec 0205, RECTIFIES spec 0155 D-6) defaults true:
 * `TaskCompletionService::complete()` then logs an IDENTICAL copy of the one
 * submitted `time_entry` for every assignee (the actor themselves when there
 * are none). `false` logs it for the acting user alone. The value is the
 * actor's own choice (the Completa dialog's checkbox, checked by default).
 *
 * `time_entry_shares` (spec 0418 D-2): on a Task with open sub-tasks the
 * submitted `time_entry` is a TOTAL split between the Task and those
 * sub-tasks, with the same per-share rules as the bulk Completa (spec 0338,
 * BulkTimeEntrySharesRules). Required there whenever `time_entry` is sent,
 * prohibited everywhere else. Which task ids the shares must cover needs the
 * sub-tree, so TaskCascadeCompletionService checks that part.
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
            ...$this->sharesRules($task),
        ];

        if (! $required && ! $this->has('time_entry')) {
            return $rules;
        }

        return array_merge($rules, TimeEntryValidationRules::rules('time_entry'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $input = $validator->getData();
            $shares = $input[BulkTimeEntrySharesRules::FIELD] ?? null;
            $timeEntry = $input['time_entry'] ?? null;
            $errors = $validator->errors();

            if (is_array($shares) && is_array($timeEntry) && ! $errors->has('time_entry.minutes')
                && ! $errors->has(BulkTimeEntrySharesRules::FIELD) && ! $errors->has(BulkTimeEntrySharesRules::FIELD.'.*')) {
                BulkTimeEntrySharesRules::validateTotal($validator, $shares, $timeEntry);
            }
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function sharesRules(mixed $task): array
    {
        $field = BulkTimeEntrySharesRules::FIELD;
        $splits = $task instanceof Task && $this->has('time_entry')
            && app(TaskActionAvailability::class)->hasOpenSubtasks($task);

        if (! $splits) {
            return [$field => ['prohibited']];
        }

        return [
            $field => ['required', 'array', 'min:1'],
            $field.'.*.task_id' => ['required', 'integer', 'distinct'],
            $field.'.*.minutes' => ['required', 'integer', 'min:1'],
        ];
    }
}
