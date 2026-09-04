<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\CreateTaskData;
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
 * `creator_id` and `completion_percentage` are `prohibited`
 * UNCONDITIONALLY, regardless of value and regardless of the actor's role
 * (AC-011): the creator is server-side (D-10) and the percentage is derived
 * from the status (D-6), so neither is ever a client input. Field
 * permissions alone cannot express this — the privileged role bypasses every
 * ceiling — so the rule lives here, ahead of and independent from that
 * mechanism.
 *
 * `start_time`/`end_time` are `H:i` TEXT (D-11): `FieldDefinition` has no
 * `time` type, and adding one to the shared catalogue is out of scope.
 *
 * Three rules are deliberately NOT here, because each is a function of the
 * RESULTING record rather than of the payload: the referente/anagrafica
 * coherence (AC-014), the sub-task hierarchy (D-12) and the closing feedback
 * (D-7). All three are enforced by App\Services\TaskService inside the write
 * transaction.
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
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'task_status_id' => ['required', 'integer', Rule::exists('task_statuses', 'id')],
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
            'requester_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'end_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_blocked' => ['sometimes', 'boolean'],
            'requires_closure_feedback' => ['sometimes', 'boolean'],
            'closure_feedback' => ['sometimes', 'nullable', 'string'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')],
            'watcher_ids' => ['sometimes', 'array'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')],
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
