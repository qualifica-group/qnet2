<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use App\Models\User;
use App\Services\DocumentChecklistService;
use App\Services\Table\FilterApplier;
use App\Tables\DocumentChecklists\DocumentChecklistColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition of the `document-checklists` domain (spec 0403). Soft-deleted
 * checklists never appear (the model's global scope); `items_count` is an
 * aggregate column and `category_name` is derived from the category relation
 * (spec 0410). The `sync` row action follows the `update` ability.
 */
class DocumentChecklistsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly DocumentChecklistService $service,
        private readonly FilterApplier $filters,
    ) {}

    public function domain(): string
    {
        return 'document-checklists';
    }

    /**
     * @return class-string<DocumentChecklist>
     */
    public function modelClass(): string
    {
        return DocumentChecklist::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives DocumentChecklistPolicy::viewAny from modelClass().

    /**
     * @return Builder<DocumentChecklist>
     */
    public function baseQuery(): Builder
    {
        return DocumentChecklist::query()->with('category')->withCount('items');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return DocumentChecklistColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return DocumentChecklistColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return DocumentChecklistColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'name', 'direction' => 'asc'],
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
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var DocumentChecklist $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'code' => $row->code,
            'category_name' => $row->category?->name,
            'is_active' => $row->is_active,
            'items_count' => (int) $row->items_count,
            'sync_status' => $row->sync_status->value,
            'sync_error' => $row->sync_error,
            'synced_at' => $row->synced_at,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Derived text filter on the category name (case-insensitive contains, or exact for `equals`).
     * The value is a bound parameter with the LIKE wildcards escaped.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'category_name') {
            return false;
        }

        $value = $filter['filter'] ?? null;
        if (! is_scalar($value) || $value === '') {
            return true;
        }

        $exact = ($filter['type'] ?? null) === 'equals';
        $query->whereHas('category', fn (Builder $category) => $exact
            ? $category->where('name', (string) $value)
            : $category->where('name', 'like', '%'.$this->filters->escapeLike((string) $value).'%'));

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'category_name') {
            return false;
        }

        $query->orderBy(
            DocumentChecklistCategory::query()->select('name')->whereColumn('document_checklist_categories.id', 'document_checklists.category_id')->limit(1),
            $direction,
        );

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $gate = Gate::forUser($actor);
        $allowed = [];

        $abilityActions = [
            'view' => ['view'],
            'delete' => ['delete'],
            'viewActivity' => ['activity'],
            'update' => ['sync', 'activate'],
        ];

        foreach ($abilityActions as $ability => $actions) {
            if ($gate->allows($ability, $row)) {
                array_push($allowed, ...$actions);
            }
        }

        return $allowed;
    }

    /**
     * Same path as the single DELETE endpoint (soft delete + remote DELETE job).
     */
    public function deleteModel(Model $model): void
    {
        /** @var DocumentChecklist $model */
        $this->service->delete($model);
    }
}
