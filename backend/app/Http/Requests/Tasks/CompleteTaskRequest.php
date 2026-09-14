<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Enums\TaskStatusGroup;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
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
 * `time_entry` is `required|array` on BOTH percorsi (D-1): the segnatempo is
 * mandatory whichever way completion goes. Its own `time_entry.*` rules come
 * from `TimeEntryValidationRules::rules('time_entry')` — the SAME source
 * `StoreTaskTimeEntryRequest` uses unprefixed (D-3, AC-009).
 *
 * Authorization stays in the controller (TaskPolicy::complete); D-2
 * deliberately does NOT check `time-entries.create` here or anywhere else in
 * this class.
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
        return array_merge(
            [
                'closure_feedback' => ['sometimes', 'nullable', 'string'],
                'validation_status_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    Rule::exists('task_statuses', 'id')->where(
                        fn ($query) => $query->where('is_active', true)->where('group', TaskStatusGroup::InValidation->value)
                    ),
                ],
                'time_entry' => ['required', 'array'],
            ],
            TimeEntryValidationRules::rules('time_entry'),
        );
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
