<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskCategories;

use App\DataObjects\TaskCategories\UpdateTaskCategoryData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\TaskCategory;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/task-categories/{taskCategory} (spec 0101,
 * D-4). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $taskCategory)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 *
 * `name` is unique ignoring self, PER PARENT (spec 0154, D-1). Checked in
 * `withValidator()` rather than an inline `Rule::unique` on `name`: either
 * `name` OR `parent_id` alone can create the (parent_id, name) collision a
 * partial PATCH allows (e.g. moving a category under a parent that already
 * has a same-named child, without touching `name` itself) — an inline rule
 * on `name` only fires when `name` is actually submitted, which would let
 * that collision reach the DB's own unique index as an uncaught 500 instead
 * of a clean 422. `color`, when submitted, cannot be null/empty
 * (`sometimes|required`); `color`/`icon` are checked against
 * App\Support\BadgeTokens' allow-lists (AC-046).
 *
 * `sort_order` and `system_key` are `prohibited` (AC-045). `parent_id`'s
 * anti-cycle guard (not itself, not one of its own descendants) is enforced
 * by TaskCategoryService, not here — it needs to walk the tree.
 */
class UpdateTaskCategoryRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskCategoryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:task_categories,id'],
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
            $this->assertNameUniquePerParent($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    /**
     * `name` unique among the siblings of the EFFECTIVE parent (spec 0154,
     * D-1): the submitted value when present, else the model's own current
     * one — so a request that moves ONLY `parent_id` is checked against the
     * category's unchanged `name`, and a request that renames ONLY `name`
     * is checked against its unchanged `parent_id`.
     */
    private function assertNameUniquePerParent(Validator $validator): void
    {
        if (! $this->has('name') && ! $this->has('parent_id')) {
            return;
        }

        $taskCategory = $this->route('taskCategory');

        if (! $taskCategory instanceof TaskCategory) {
            return;
        }

        $name = $this->has('name') ? (string) $this->input('name') : $taskCategory->name;
        $parentId = $this->has('parent_id') ? $this->input('parent_id') : $taskCategory->parent_id;

        $conflict = TaskCategory::query()
            ->where('name', $name)
            ->where('id', '!=', $taskCategory->id)
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'), fn ($query) => $query->where('parent_id', $parentId))
            ->exists();

        if ($conflict) {
            $validator->errors()->add('name', 'The name has already been taken.');
        }
    }

    protected function authorizationResource(): string
    {
        return 'task-categories';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var TaskCategory $taskCategory */
        $taskCategory = $this->route('taskCategory');

        return $taskCategory;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTaskCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTaskCategoryData::fromValidated($validated);
    }
}
