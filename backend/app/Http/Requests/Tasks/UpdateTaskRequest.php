<?php

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\UpdateTaskData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/tasks/{task} (spec 0101). Every
 * field is `sometimes` to support partial PATCH updates; the four columns
 * that are NOT NULL in the database are `sometimes|required`, so an explicit
 * null is a 422 from validation rather than an error from the driver.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $task)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 *
 * `creator_id` and `completion_percentage` are `prohibited` UNCONDITIONALLY
 * — same reasoning as StoreTaskRequest: the creator is immutable and
 * server-owned (D-10) and the percentage is derived (D-6), and neither
 * statement may be weakened by a role's field-permission matrix.
 *
 * `assignee_ids`/`watcher_ids` are full-replaced by the Service ONLY when
 * their own key is present in the payload (AC-012), so a PATCH that touches
 * the title alone leaves both pivots untouched.
 */
class UpdateTaskRequest extends FormRequest
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
            'requester_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'completion_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'end_time' => ['sometimes', 'nullable', 'string', 'date_format:'.self::TIME_FORMAT],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_blocked' => ['sometimes', 'required', 'boolean'],
            'requires_closure_feedback' => ['sometimes', 'required', 'boolean'],
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
