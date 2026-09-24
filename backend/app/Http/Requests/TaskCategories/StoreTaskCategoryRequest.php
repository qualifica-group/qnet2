<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskCategories;

use App\DataObjects\TaskCategories\CreateTaskCategoryData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validates the payload for POST /api/task-categories (spec 0101, D-4).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', TaskCategory::class)).
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
 *
 * `parent_id` (spec 0154, D-1) is optional (null = a root category); a cycle
 * is structurally impossible on create (the row has no id yet), so only
 * `exists` is checked here. `name` is unique PER PARENT, including among
 * roots (both NULL) — a plain `Rule::unique` treats NULLs as distinct, so
 * the scope is applied explicitly via `where()`.
 */
class StoreTaskCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, $this->uniqueNamePerParent()],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:task_categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'color' => ['required', 'string', Rule::in(BadgeTokens::colors())],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(BadgeTokens::icons())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['prohibited'],
            'system_key' => ['prohibited'],
        ];
    }

    /**
     * `name` unique among the siblings of the SUBMITTED `parent_id` (null
     * included, via `whereNull`) — never globally, since D-1 allows the same
     * name to reappear under a different parent.
     */
    private function uniqueNamePerParent(): Unique
    {
        $parentId = $this->input('parent_id');

        return Rule::unique('task_categories', 'name')->where(
            fn ($query) => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'task-categories';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTaskCategoryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTaskCategoryData::fromValidated($validated);
    }
}
