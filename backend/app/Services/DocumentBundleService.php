<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\DocumentBundles\CreateDocumentBundleData;
use App\DataObjects\DocumentBundles\UpdateDocumentBundleData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\DocumentBundle;
use Illuminate\Support\Collection;

/**
 * Business logic for the `document-bundles` resource (spec 0175, D-7c/D-14):
 * a named set of files ("Modello documenti") the Commessa email composer can
 * attach in bulk. The files themselves are uploaded/removed through the
 * existing `/api/attachments` endpoints (alias `document_bundle`) — never
 * here. The controller stays thin; this Service is the single authority.
 */
class DocumentBundleService
{
    /** @var array<int, string> */
    private const array FOR_SELECT_COLUMNS = ['id', 'name', 'is_active'];

    public function create(CreateDocumentBundleData $data): DocumentBundle
    {
        /** @var DocumentBundle $documentBundle */
        $documentBundle = DocumentBundle::create($data->attributes());

        return $this->withFilesCount($documentBundle);
    }

    public function update(DocumentBundle $documentBundle, UpdateDocumentBundleData $data): DocumentBundle
    {
        // Unconditional save: fires the model's saved event even when no
        // native attribute changed (mirrors TaskImportanceService::update).
        $documentBundle->fill($data->submittedAttributes())->save();

        return $this->withFilesCount($documentBundle->fresh());
    }

    public function delete(DocumentBundle $documentBundle): void
    {
        // HasAttachments::bootHasAttachments() cascades: every file under
        // this bundle (collection `documents`) is removed with it.
        $documentBundle->delete();
    }

    /**
     * Attaches `files_count` (DocumentBundleResource's contract) so every
     * caller — create/update here, and the controller's own show() — never
     * falls back to an extra ad hoc query (mirrors OpportunityResource's
     * `quotes_count`, populated by OpportunityService::loadDetail()).
     */
    public function withFilesCount(DocumentBundle $documentBundle): DocumentBundle
    {
        return $documentBundle->loadCount(['attachments as files_count']);
    }

    /**
     * Minimal, searchable, paginated, is_active-only list for the for-select
     * standard (ADR 0011). `meta.files_count` lets the picker preview the
     * bundle's file count before opening it.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = DocumentBundle::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->withCount(['attachments as files_count'])
            ->where('is_active', true);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, DocumentBundle> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @param  Collection<int, DocumentBundle>  $page
     * @return Collection<int, DocumentBundle>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, DocumentBundle> $hydrated */
        $hydrated = DocumentBundle::query()
            ->select(self::FOR_SELECT_COLUMNS)
            ->withCount(['attachments as files_count'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
