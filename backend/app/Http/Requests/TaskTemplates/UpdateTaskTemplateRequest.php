<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates;

use App\DataObjects\TaskTemplates\UpdateTaskTemplateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateItems;
use App\Http\Requests\TaskTemplates\Concerns\ValidatesTaskTemplateStages;
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
 * `stages` (spec 0146, D-2) follows the identical full-sync shape one level
 * up: a `stages.*.id`, if present, must belong to THIS template (AC-003),
 * and every `items.*.stage_key` must resolve to a `stages.*.key` of the
 * SAME request (AC-003) — both asserted here, ahead of
 * App\Services\TaskTemplates\TaskTemplateStageWriter::sync.
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
    use ValidatesTaskTemplateStages;

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
            ...$this->stagesRules(allowIds: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->assertItemIdsBelongToTemplate($validator);
            $this->assertStageIdsBelongToTemplate($validator);
            $this->assertItemStageKeysResolve($validator);
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

    /**
     * Every submitted `stages.*.id` must resolve to a row already owned by
     * THIS template — a foreign or unknown id 422s on `stages.N.id`, and
     * neither template is touched (AC-003).
     */
    private function assertStageIdsBelongToTemplate(Validator $validator): void
    {
        $stages = $this->input('stages');

        if (! is_array($stages)) {
            return;
        }

        /** @var TaskTemplate $taskTemplate */
        $taskTemplate = $this->route('taskTemplate');
        $ownedIds = $taskTemplate->stages()->pluck('id')->all();

        foreach ($stages as $index => $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;

            if ($id !== null && ! in_array((int) $id, $ownedIds, true)) {
                $validator->errors()->add("stages.{$index}.id", 'This stage does not belong to this task template.');
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
