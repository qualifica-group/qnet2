<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskImportances;

use App\DataObjects\TaskImportances\UpdateTaskImportanceData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\TaskImportance;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/task-importances/{taskImportance} (spec 0101,
 * D-4). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $taskImportance)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `name` is unique ignoring self; `color`, when submitted, cannot be
 * null/empty (`sometimes|required`); `color`/`icon` are checked against
 * App\Support\BadgeTokens' allow-lists (AC-046).
 *
 * `sort_order` and `system_key` are `prohibited` (AC-045).
 */
class UpdateTaskImportanceRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskImportancePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $taskImportance = $this->route('taskImportance');
        $ignoreId = $taskImportance instanceof TaskImportance ? $taskImportance->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_importances', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['sometimes', 'required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'is_active' => ['sometimes', 'boolean'],
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
        return 'task-importances';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var TaskImportance $taskImportance */
        $taskImportance = $this->route('taskImportance');

        return $taskImportance;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTaskImportanceData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTaskImportanceData::fromValidated($validated);
    }
}
