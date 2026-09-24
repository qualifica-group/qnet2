<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskCategoryService;
use App\Tables\TaskCategories\TaskCategoryColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `task-categories` domain (spec 0101, D-4).
 *
 * Real columns (name, description, color, icon, sort_order, is_active,
 * created_at) are handled entirely by the generic engine. `parent` (spec
 * 0154, D-1) is DERIVED — no real DB column, only `parent_id` — the related
 * parent's name, mirroring ProductCategoriesTableDefinition's own `parent`.
 */
class TaskCategoriesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `parent` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly TaskCategoryService $service) {}

    public function domain(): string
    {
        return 'task-categories';
    }

    /**
     * @return class-string<TaskCategory>
     */
    public function modelClass(): string
    {
        return TaskCategory::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives TaskCategoryPolicy::viewAny
    // from modelClass() (task-categories.viewAny).

    /**
     * @return Builder<TaskCategory>
     */
    public function baseQuery(): Builder
    {
        return TaskCategory::query()->with('parent');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return TaskCategoryColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return TaskCategoryColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return TaskCategoryColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map a TaskCategory to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var TaskCategory $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'parent' => $this->parentSummary($row->parent),
            'description' => $row->description,
            'color' => $row->color,
            'icon' => $row->icon,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function parentSummary(?TaskCategory $parent): ?array
    {
        if ($parent === null) {
            return null;
        }

        return ['id' => $parent->id, 'name' => $parent->name];
    }

    /**
     * Allowed action keys for a single row, via TaskCategoryPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var TaskCategory $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the derived `parent` set filter. Every other column id (the
     * real columns) falls through to the generic engine.
     *
     * @param  Builder<TaskCategory>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        return $this->filterByParentName($query, $filter);
    }

    /**
     * Derived set filter via whereHas on the self-referencing `parent`
     * relation, matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<TaskCategory>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByParentName(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        // AG Grid's blank entry ("(Vuoti)") arrives as a null among the values:
        // here it selects the ROOT categories, which have no parent at all.
        $matchesBlank = in_array(null, $values, true);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('parent', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                });
            }

            if ($matchesBlank) {
                $group->orWhereNull('parent_id');
            }
        });

        return true;
    }

    /**
     * ORDER BY the parent's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<TaskCategory>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        // Self-join: the subquery's own table is aliased (`parent_category`)
        // so it never collides with the outer query's `task_categories`.
        $subquery = TaskCategory::query()
            ->from('task_categories as parent_category')
            ->select('parent_category.name')
            ->whereColumn('parent_category.id', 'task_categories.parent_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `parent`
     * column.
     *
     * @param  Builder<TaskCategory>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== 'parent') {
            return null;
        }

        return $this->distinctParentNames($query, $search, $limit);
    }

    /**
     * @param  Builder<TaskCategory>  $query
     * @return array<int, string|null>
     */
    private function distinctParentNames(Builder $query, ?string $search, int $limit): array
    {
        $parentIds = (clone $query)->whereNotNull('parent_id')->select('parent_id');

        $names = DB::table('task_categories')
            ->whereIn('id', $parentIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        // Blank entry ("(Vuoti)"): the root categories of the current scope.
        if (($search === null || $search === '') && (clone $query)->whereNull('parent_id')->exists()) {
            array_unshift($names, null);
        }

        return $names;
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Delegate to TaskCategoryService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (in use by a Task, D-8b; still
     * parenting a category, D-1) as the single DELETE
     * /task-categories/{taskCategory} endpoint (AC-041).
     */
    public function deleteModel(Model $model): void
    {
        /** @var TaskCategory $model */
        $this->service->delete($model);
    }
}
