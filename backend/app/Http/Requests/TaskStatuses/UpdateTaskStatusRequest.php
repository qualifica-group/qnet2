<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskStatuses;

use App\DataObjects\TaskStatuses\UpdateTaskStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\TaskStatus;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/task-statuses/{taskStatus} (spec 0101,
 * D-4). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $taskStatus)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `name` is unique ignoring self; `color`, when submitted, cannot be
 * null/empty (`sometimes|required`); `color`/`icon` are checked against
 * App\Support\BadgeTokens' allow-lists (AC-046).
 *
 * `sort_order` and `system_key` are `prohibited` (AC-045).
 * On a SYSTEM row (`system_key` valorised) every submitted key beyond
 * name/color/icon/completion_percentage is rejected one layer further in, by
 * App\Services\Statuses\SystemStatusGuard (AC-043): the guard needs the
 * PERSISTED row to know whether it is a system one, which a FormRequest rule
 * cannot express.
 */
class UpdateTaskStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $taskStatus = $this->route('taskStatus');
        $ignoreId = $taskStatus instanceof TaskStatus ? $taskStatus->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_statuses', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'is_active' => ['sometimes', 'boolean'],
            'completion_percentage' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'sort_order' => ['prohibited'],
            'system_key' => ['prohibited'],
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
        return 'task-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var TaskStatus $taskStatus */
        $taskStatus = $this->route('taskStatus');

        return $taskStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTaskStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTaskStatusData::fromValidated($validated);
    }
}
