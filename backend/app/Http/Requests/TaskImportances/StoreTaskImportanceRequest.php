<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskImportances;

use App\DataObjects\TaskImportances\CreateTaskImportanceData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/task-importances (spec 0101, D-4).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', TaskImportance::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null).
 *
 * `color`/`icon` are validated against the SERVER-SIDE allow-lists of
 * App\Support\BadgeTokens (AC-046): a palette token and a curated lucide
 * name, never a hex value nor free text.
 *
 * `sort_order` and `system_key` carry an explicit `prohibited` rule rather than
 * simply being absent from rules(): AC-045 requires a 422, and a merely
 * absent key would be silently dropped by validated() instead.
 */
class StoreTaskImportanceRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_importances', 'name')],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['required', 'string', Rule::in(BadgeTokens::colors())],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskImportanceData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskImportanceData::fromValidated($validated);
    }
}
