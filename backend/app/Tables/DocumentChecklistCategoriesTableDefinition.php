<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\DocumentChecklistCategory;
use App\Models\User;
use App\Services\DocumentChecklistCategoryService;
use App\Tables\DocumentChecklistCategories\DocumentChecklistCategoryColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition of the `document-checklist-categories` domain (spec 0410).
 * Soft-deleted categories never appear (the model's global scope); `checklists_count`
 * counts the non-deleted checklists. The `sync` row action follows the `update`
 * ability, all abilities being the document-checklists.* ones (D-2).
 */
class DocumentChecklistCategoriesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly DocumentChecklistCategoryService $service) {}

    public function domain(): string
    {
        return 'document-checklist-categories';
    }

    /**
     * @return class-string<DocumentChecklistCategory>
     */
    public function modelClass(): string
    {
        return DocumentChecklistCategory::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // derives DocumentChecklistCategoryPolicy::viewAny from modelClass().

    /**
     * @return Builder<DocumentChecklistCategory>
     */
    public function baseQuery(): Builder
    {
        return DocumentChecklistCategory::query()->withCount('checklists');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return DocumentChecklistCategoryColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return DocumentChecklistCategoryColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return DocumentChecklistCategoryColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
        /** @var DocumentChecklistCategory $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'description' => $row->description,
            'sort_order' => $row->sort_order,
            'checklists_count' => (int) $row->checklists_count,
            'sync_status' => $row->sync_status->value,
            'sync_error' => $row->sync_error,
            'synced_at' => $row->synced_at,
            'created_at' => $row->created_at,
        ];
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
            'update' => ['sync'],
        ];

        foreach ($abilityActions as $ability => $actions) {
            if ($gate->allows($ability, $row)) {
                array_push($allowed, ...$actions);
            }
        }

        return $allowed;
    }

    /**
     * Same path as the single DELETE endpoint: refused while the category holds
     * checklists (ValidationException, caught by the bulk delete as a refusal).
     */
    public function deleteModel(Model $model): void
    {
        /** @var DocumentChecklistCategory $model */
        $this->service->delete($model);
    }
}
