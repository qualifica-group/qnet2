<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskStatuses;

use App\DataObjects\TaskStatuses\CreateTaskStatusData;
use App\Enums\TaskStatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/task-statuses (spec 0101, D-4).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', TaskStatus::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null).
 *
 * `color`/`icon` are validated against the SERVER-SIDE allow-lists of
 * App\Support\BadgeTokens (AC-046): a palette token and a curated lucide
 * name, never a hex value nor free text. `group` is REQUIRED and comes from
 * App\Enums\TaskStatusGroup: every row declares the phase it belongs to,
 * there is no neutral "no phase" value (D-5 as rectified 2026-09-04).
 *
 * `sort_order` and `system_key` carry an explicit `prohibited` rule rather than
 * simply being absent from rules(): AC-045 requires a 422, and a merely
 * absent key would be silently dropped by validated() instead.
 *
 * `group` = in_validation forces `completion_percentage` = 100 (spec 0126,
 * D-5): validation is a gate, not a stage of progress, so a task waiting on
 * it already reads as done. Checked in withValidator() rather than rules(),
 * since it is a cross-field rule.
 */
class StoreTaskStatusRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_statuses', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'is_active' => ['sometimes', 'boolean'],
            'completion_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'group' => ['required', 'string', Rule::enum(TaskStatusGroup::class)],
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
     * A status in the `in_validation` phase must carry `completion_percentage`
     * = 100 (spec 0126, D-5). Skipped once `group`/`completion_percentage`
     * already failed their own rule, so this never doubles up on the base
     * "required"/"in" errors.
     */
    private function assertInValidationIsFullyComplete(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['group', 'completion_percentage'])) {
            return;
        }

        $group = TaskStatusGroup::tryFrom((string) $this->input('group'));
        $percentage = (int) $this->input('completion_percentage');

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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskStatusData::fromValidated($validated);
    }
}
