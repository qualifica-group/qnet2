<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\UpdateTaskData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/tasks/{task} (spec 0101). Every
 * field is `sometimes` to support partial PATCH updates; the columns that
 * are NOT NULL in the database are `sometimes|required`, so an explicit
 * null is a 422 from validation rather than an error from the driver.
 *
 * `requester_id`, `assignee_ids` and `end_date` follow the same
 * `sometimes|required` shape since spec 0118 D-2: not annullable once the
 * key is submitted, but a PATCH that does not name them still passes.
 * `assignee_ids` additionally keeps `min:1` when submitted — a Task can no
 * longer be emptied of every assignee (AC-032 of spec 0101 no longer
 * applies: D-1 retired the "zero assignees" state for good, not only at
 * creation). `task_status_id` is DELIBERATELY untouched here: spec 0118 D-6
 * derives it only at creation, so on PATCH the actor still picks it by hand,
 * exactly as before.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $task)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 *
 * `creator_id`, `completion_percentage` and `is_blocked` are `prohibited`
 * UNCONDITIONALLY — same reasoning as StoreTaskRequest: the creator is
 * immutable and server-owned (D-10), the percentage is derived (D-6), and
 * `is_blocked` is written ONLY by the block/unblock domain actions, never by
 * a PATCH (spec 0116 D-6) — exactly how the Contracts module treats
 * `validated_at`/`terminated_at`. None of the three statements may be
 * weakened by a role's field-permission matrix: the privileged role bypasses
 * every ceiling, so the rule lives here, ahead of and independent from that
 * mechanism (AC-035).
 *
 * `assignee_ids`/`watcher_ids` are full-replaced by the Service ONLY when
 * their own key is present in the payload (AC-012), so a PATCH that touches
 * the title alone leaves both pivots untouched.
 *
 * `recurrence` (spec 0120, data_contract) carries the three-way PATCH
 * semantic every other nullable-COLUMN field does not need: absent leaves
 * the series as it is, `null` cancels it, an object creates or replaces it.
 * Its own gate is D-12/AC-028: unlike every other field in
 * `TaskAbilityResolver::PROTECTED_FIELDS` (422 via EnforcesFieldPermissions'
 * change-based diff, see that trait), a non-mandate actor submitting
 * `recurrence` AT ALL — including a no-op resubmission — is refused with a
 * genuine 403, so the check lives in authorize() below, ahead of validation
 * entirely, rather than in the shared trait every other field uses.
 */
class UpdateTaskRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int TITLE_MAX = 191;

    private const string TIME_FORMAT = 'H:i';

    public function authorize(): bool
    {
        // The base `update` ability stays in the controller via TaskPolicy.
        // `recurrence` gets its OWN gate here (spec 0120 D-12, AC-028): the
        // record-role mandate check on the series is a genuine 403, not the
        // 422 every other protected field gets from EnforcesFieldPermissions
        // — an actor without the mandate may not touch `recurrence` at all,
        // not even resubmit its current value as a no-op.
        if ($this->has('recurrence')) {
            /** @var Task $task */
            $task = $this->route('task');
            /** @var User $actor */
            $actor = $this->user();

            return TaskAbilityResolver::canUpdateProtectedFields($actor, $task);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'creator_id' => ['prohibited'],
            'completion_percentage' => ['prohibited'],
            'is_blocked' => ['prohibited'],
            'task_recurrence_id' => ['prohibited'],
            'title' => ['sometimes', 'required', 'string', 'max:'.self::TITLE_MAX],
            'task_status_id' => ['sometimes', 'required', 'integer', Rule::exists('task_statuses', 'id')],
            'description' => ['sometimes', 'nullable', 'string'],
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
            'referent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'parent_task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')],
            'task_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_types', 'id')],
            'task_priority_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_priorities', 'id')],
            'task_importance_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_importances', 'id')],
            'task_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],
            'work_order_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_orders', 'id')],
            'requester_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'required', 'date'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'end_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'requires_closure_feedback' => ['sometimes', 'required', 'boolean'],
            'requires_validation' => ['sometimes', 'required', 'boolean'],
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'assignee_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],
            'watcher_ids' => ['sometimes', 'array'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')],
            ...$this->recurrenceRules(),
        ];
    }

    /**
     * Identical shape to StoreTaskRequest's own (data_contract): the PATCH
     * endpoint validates the SUBMITTED `recurrence` object the same way,
     * regardless of what three-way instruction it ends up carrying —
     * `null`/absent never reach these inner rules at all (`sometimes`).
     *
     * @return array<string, array<int, mixed>>
     */
    private function recurrenceRules(): array
    {
        return [
            'recurrence' => ['sometimes', 'nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', Rule::enum(TaskRecurrenceFrequency::class)],
            'recurrence.interval' => ['required_with:recurrence', 'integer', 'min:1'],
            'recurrence.weekdays' => ['required_if:recurrence.frequency,weekly', 'prohibited_unless:recurrence.frequency,weekly', 'array', 'min:1'],
            'recurrence.weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'recurrence.month_day' => ['required_if:recurrence.frequency,monthly', 'prohibited_unless:recurrence.frequency,monthly', 'integer', 'between:1,31'],
            'recurrence.ends' => ['required_with:recurrence', Rule::enum(TaskRecurrenceEnd::class)],
            'recurrence.ends_on' => ['required_if:recurrence.ends,on_date', 'prohibited_unless:recurrence.ends,on_date', 'date', 'after:end_date'],
            'recurrence.occurrence_count' => ['required_if:recurrence.ends,after_count', 'prohibited_unless:recurrence.ends,after_count', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'tasks';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Task $task */
        $task = $this->route('task');

        return $task;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTaskData::fromValidated($validated);
    }
}
