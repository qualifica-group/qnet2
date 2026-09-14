<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates;

use App\DataObjects\TaskTemplates\UpdateTaskTemplateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateItems;
use App\Models\TaskTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/task-templates/{taskTemplate}
 * (spec 0124, D-1). Every field is `sometimes` to support partial PATCH
 * updates. When `items` IS submitted it is a FULL sync (AC-004): a row's
 * `id`, if present, must belong to THIS template — asserted here, ahead of
 * App\Services\TaskTemplates\TaskTemplateItemWriter::sync, which trusts
 * every id it receives (AC-005).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $taskTemplate)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit on this specific model.
 */
class UpdateTaskTemplateRequest extends FormRequest
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
        $taskTemplate = $this->route('taskTemplate');
        $ignoreId = $taskTemplate instanceof TaskTemplate ? $taskTemplate->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, Rule::unique('task_templates', 'name')->ignore($ignoreId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->itemsRules(required: false, allowIds: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->assertItemIdsBelongToTemplate($validator);
        });
    }

    /**
     * Every submitted `items.*.id` must resolve to a row already owned by
     * THIS template — a foreign or unknown id 422s on `items.N.id`, and
     * neither template is touched (AC-005).
     */
    private function assertItemIdsBelongToTemplate(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        /** @var TaskTemplate $taskTemplate */
        $taskTemplate = $this->route('taskTemplate');
        $ownedIds = $taskTemplate->items()->pluck('id')->all();

        foreach ($items as $index => $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;

            if ($id !== null && ! in_array((int) $id, $ownedIds, true)) {
                $validator->errors()->add("items.{$index}.id", 'This row does not belong to this task template.');
            }
        }
    }

    protected function authorizationResource(): string
    {
        return 'task-templates';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var TaskTemplate $taskTemplate */
        $taskTemplate = $this->route('taskTemplate');

        return $taskTemplate;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTaskTemplateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTaskTemplateData::fromValidated($validated);
    }
}
