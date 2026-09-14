<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CreateTaskData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks (spec 0101).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Task::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 *
 * Five fields are `prohibited` UNCONDITIONALLY, regardless of value and
 * regardless of the actor's role (AC-011/AC-035/AC-009): `creator_id`, the
 * creator is server-side (D-10); `completion_percentage`, derived from the
 * status (D-6); `is_blocked`, written ONLY by the block/unblock domain
 * actions, never at creation time (spec 0116 D-6); and, since spec 0118 D-3,
 * `task_status_id` — the initial status is now DERIVED server-side by
 * `App\Services\Tasks\TaskInitialStatusResolver` (D-4) from the assignees/
 * creator/requester, so it is no longer a client input either. Since spec 0127
 * D-1 `completion_date` joins them: only the completion actions
 * (TaskCompletionService) write it, so a negative close never carries one. Field
 * permissions alone cannot express this — the privileged role bypasses
 * every ceiling — so the rule lives here, ahead of and independent from
 * that mechanism.
 *
 * `start_time`/`end_time` are `H:i` TEXT (D-11): `FieldDefinition` has no
 * `time` type, and adding one to the shared catalogue is out of scope.
 *
 * Three rules are deliberately NOT here, because each is a function of the
 * RESULTING record rather than of the payload: the referente/anagrafica
 * coherence (AC-014), the sub-task hierarchy (D-12) and the closing feedback
 * (D-7). All three are enforced by App\Services\TaskService inside the write
 * transaction.
 *
 * `recurrence` (spec 0120, data_contract) is `sometimes|nullable|array`, with
 * every inner field conditioned on `frequency`/`ends` via
 * `required_if`/`prohibited_unless` pairs — the same pattern used nowhere
 * else in this file because no other field on the catalogue has an internal
 * shape of its own. `task_recurrence_id` is `prohibited` alongside the other
 * server-owned columns: no payload ever names the row directly, only the
 * `recurrence` object App\Services\Tasks\TaskRecurrenceService turns into
 * one. AC-027's "recurrence without an end_date" is a SEPARATE check, added
 * in withValidator() below: `end_date` is already unconditionally required
 * on POST, so this never independently blocks a request — it exists only to
 * put `recurrence` itself in the error bag, which is what the criterion
 * asserts on.
 */
class StoreTaskRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int TITLE_MAX = 191;

    private const string TIME_FORMAT = 'H:i';

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskPolicy.
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
            'task_status_id' => ['prohibited'],
            'task_recurrence_id' => ['prohibited'],
            'completion_date' => ['prohibited'],
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
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
            'requester_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['required', 'date'],
            'start_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'end_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'requires_closure_feedback' => ['sometimes', 'boolean'],
            'requires_validation' => ['sometimes', 'boolean'],
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],
            'watcher_ids' => ['sometimes', 'array'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')],
            ...$this->recurrenceRules(),
        ];
    }

    /**
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

            // AC-027: a recurrence needs an end_date to anchor its
            // occurrences (D-5). `end_date` is already `required` above, so
            // this fires alongside it, not instead of it — the criterion
            // only asserts `recurrence` is in the error bag too.
            if ($this->filled('recurrence') && ! $this->filled('end_date')) {
                $validator->errors()->add('recurrence', 'A recurrence requires an end date.');
            }
        });
    }

    protected function authorizationResource(): string
    {
        return 'tasks';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskData::fromValidated($validated);
    }
}
