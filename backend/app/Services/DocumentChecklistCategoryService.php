<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\DocumentChecklistCategories\SaveDocumentChecklistCategoryData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\SyncDocumentChecklistCategoryJob;
use App\Models\DocumentChecklistCategory;
use App\Models\DocumentChecklistCategorySyncLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic of the `document-checklist-categories` resource (spec 0410).
 * Every write leaves the category `pending` and queues
 * SyncDocumentChecklistCategoryJob after the commit; the job reads the state
 * when it runs. Editing a category never re-queues its checklists (D-8): they
 * reference it by external_id.
 */
class DocumentChecklistCategoryService
{
    public function create(SaveDocumentChecklistCategoryData $data): DocumentChecklistCategory
    {
        $category = DocumentChecklistCategory::create([
            ...$data->attributes(),
            'sync_status' => DocumentChecklistSyncStatus::Pending,
        ]);

        $this->queueSync($category);

        return $this->loadDetail($category);
    }

    public function update(DocumentChecklistCategory $category, SaveDocumentChecklistCategoryData $data): DocumentChecklistCategory
    {
        $category->fill($data->attributes());
        $this->markPending($category);
        $this->queueSync($category);

        return $this->loadDetail($category);
    }

    /**
     * Soft delete: the row stays as the trace of the remote id (D-5) and the job
     * issues the remote DELETE. Refused while the category still holds
     * non-deleted checklists (D-3). The bulk delete of the table goes through here too.
     *
     * @throws ValidationException when the category still contains checklists
     */
    public function delete(DocumentChecklistCategory $category): void
    {
        // Step 1: a category with checklists is never deleted (no automatic move)
        $checklistsCount = $category->checklists()->count();
        if ($checklistsCount > 0) {
            throw ValidationException::withMessages([
                'category' => __('document_checklists.category_has_checklists', ['count' => $checklistsCount]),
            ]);
        }

        // Step 2: soft delete + pending in one transaction, then the remote DELETE
        DB::transaction(function () use ($category): void {
            $this->markPending($category);
            $category->delete();
        });

        $this->queueSync($category);
    }

    public function requestSync(DocumentChecklistCategory $category): DocumentChecklistCategory
    {
        $this->markPending($category);
        $this->queueSync($category);

        return $this->loadDetail($category);
    }

    /**
     * One job per non-deleted category; returns how many were queued.
     */
    public function requestSyncAll(): int
    {
        $categories = DocumentChecklistCategory::query()->get();

        foreach ($categories as $category) {
            $this->markPending($category);
            $this->queueSync($category);
        }

        return $categories->count();
    }

    /**
     * @return Collection<int, DocumentChecklistCategorySyncLog>
     */
    public function recentSyncLogs(DocumentChecklistCategory $category): Collection
    {
        return $category->syncLogs()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('archivio-smart.sync_log_display_limit'))
            ->get();
    }

    public function loadDetail(DocumentChecklistCategory $category): DocumentChecklistCategory
    {
        return $category->loadCount('checklists');
    }

    /**
     * Non-deleted categories for the for-select standard (ADR 0011), ordered like
     * the grid: sort_order, name.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = DocumentChecklistCategory::query()->select(['id', 'name', 'sort_order']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, DocumentChecklistCategory> $page */
        $page = $this->ordered($base)->offset($query->offset)->limit($query->limit)->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @param  Collection<int, DocumentChecklistCategory>  $page
     * @return Collection<int, DocumentChecklistCategory>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));
        if ($missingIds === []) {
            return $page;
        }

        $hydrated = $this->ordered(DocumentChecklistCategory::query()->select(['id', 'name', 'sort_order'])->whereIn('id', $missingIds))->get();

        return $page->concat($hydrated);
    }

    /**
     * @param  Builder<DocumentChecklistCategory>  $query
     * @return Builder<DocumentChecklistCategory>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    private function markPending(DocumentChecklistCategory $category): void
    {
        $category->forceFill([
            'sync_status' => DocumentChecklistSyncStatus::Pending,
            'sync_error' => null,
        ])->save();
    }

    private function queueSync(DocumentChecklistCategory $category): void
    {
        SyncDocumentChecklistCategoryJob::dispatch($category->id)->afterCommit();
    }
}
