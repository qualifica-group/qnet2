<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates;

use App\DataObjects\TaskTemplates\CreateTaskTemplateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateItems;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/task-templates (spec 0124, D-1).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', TaskTemplate::class)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null).
 */
class StoreTaskTemplateRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesTaskTemplateItems;

    private const int NAME_MAX = 191;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskTemplatePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_templates', 'name')],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->itemsRules(required: true, allowIds: false),
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
        return 'task-templates';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskTemplateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskTemplateData::fromValidated($validated);
    }
}
