<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskStatuses;

use App\DataObjects\TaskStatuses\UpdateTaskStatusData;
use App\Enums\TaskStatusGroup;
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
 * `group` is `sometimes|required`: submitting it null/empty is a 422, not a
 * way back to "no phase".
 * On a SYSTEM row (`system_key` valorised) every submitted key beyond
 * name/color/icon/completion_percentage is rejected one layer further in, by
 * App\Services\Statuses\SystemStatusGuard (AC-043): the guard needs the
 * PERSISTED row to know whether it is a system one, which a FormRequest rule
 * cannot express.
 *
 * The RESULTING `group` = in_validation forces the RESULTING
 * `completion_percentage` = 100 (spec 0126, D-5). "Resulting" because either
 * field can be a PATCH that omits the other: a percentage-only PATCH on an
 * already in_validation row reads the phase off the PERSISTED model, and a
 * group-only PATCH into in_validation reads the percentage off it the same
 * way — the FormRequest cannot see the request in isolation from what is
 * already on the row.
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
            'group' => ['sometimes', 'required', 'string', Rule::enum(TaskStatusGroup::class)],
            'sort_order' => ['prohibited'],
            'system_key' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->assertInValidationIsFullyComplete($validator);
        });
    }

    /**
     * See the class docblock: checks the RESULTING group/percentage, falling
     * back to the persisted row for whichever of the two this PATCH does not
     * resend. Skipped once `group`/`completion_percentage` already failed
     * their own rule, so this never doubles up on the base errors.
     */
    private function assertInValidationIsFullyComplete(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['group', 'completion_percentage'])) {
            return;
        }

        $taskStatus = $this->route('taskStatus');
        $group = $this->has('group')
            ? TaskStatusGroup::tryFrom((string) $this->input('group'))
            : $taskStatus?->group;
        $percentage = $this->has('completion_percentage')
            ? (int) $this->input('completion_percentage')
            : $taskStatus?->completion_percentage;

        if ($group === TaskStatusGroup::InValidation && $percentage !== 100) {
            $validator->errors()->add('completion_percentage', 'A status in the in_validation phase must have a completion percentage of 100.');
        }
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
