<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\DocumentChecklists\SaveDocumentChecklistData;
use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\SyncDocumentChecklistJob;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistSyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic of the `document-checklists` resource (spec 0403). Every write
 * leaves the checklist `pending` and queues SyncDocumentChecklistJob after the
 * commit; the job (not this service) talks to ArchivioSmart and reads the
 * checklist state when it runs.
 */
class DocumentChecklistService
{
    public function create(SaveDocumentChecklistData $data): DocumentChecklist
    {
        // Step 1: checklist + sections + items in one transaction
        $checklist = DB::transaction(function () use ($data): DocumentChecklist {
            $checklist = DocumentChecklist::create([
                ...$data->attributes(),
                'sync_status' => DocumentChecklistSyncStatus::Pending,
            ]);
            $this->replaceContent($checklist, $data);

            return $checklist;
        });

        // Step 2: queue the remote sync
        $this->queueSync($checklist);

        return $this->loadDetail($checklist);
    }

    public function update(DocumentChecklist $checklist, SaveDocumentChecklistData $data): DocumentChecklist
    {
        DB::transaction(function () use ($checklist, $data): void {
            $checklist->fill([
                ...$data->attributes(),
                'sync_status' => DocumentChecklistSyncStatus::Pending,
                'sync_error' => null,
            ])->save();
            $this->replaceContent($checklist, $data);
        });

        $this->queueSync($checklist);

        return $this->loadDetail($checklist);
    }

    public function setActivation(DocumentChecklist $checklist, bool $isActive): DocumentChecklist
    {
        $this->markPending($checklist, ['is_active' => $isActive]);
        $this->queueSync($checklist);

        return $this->loadDetail($checklist);
    }

    /**
     * Soft delete: the row stays as the trace of the remote id (D-3) and the job
     * issues the remote DELETE. The bulk delete of the table goes through here too.
     */
    public function delete(DocumentChecklist $checklist): void
    {
        DB::transaction(function () use ($checklist): void {
            $this->markPending($checklist);
            $checklist->delete();
        });

        $this->queueSync($checklist);
    }

    public function requestSync(DocumentChecklist $checklist): DocumentChecklist
    {
        $this->markPending($checklist);
        $this->queueSync($checklist);

        return $this->loadDetail($checklist);
    }

    /**
     * One job per non-deleted checklist; returns how many were queued.
     */
    public function requestSyncAll(): int
    {
        $checklists = DocumentChecklist::query()->get();

        foreach ($checklists as $checklist) {
            $this->markPending($checklist);
            $this->queueSync($checklist);
        }

        return $checklists->count();
    }

    /**
     * @return Collection<int, DocumentChecklistSyncLog>
     */
    public function recentSyncLogs(DocumentChecklist $checklist): Collection
    {
        return $checklist->syncLogs()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('archivio-smart.sync_log_display_limit'))
            ->get();
    }

    public function loadDetail(DocumentChecklist $checklist): DocumentChecklist
    {
        return $checklist->unsetRelation('items')->unsetRelation('sections')->load(['items', 'sections', 'category']);
    }

    private function replaceContent(DocumentChecklist $checklist, SaveDocumentChecklistData $data): void
    {
        // Step 1: sections first, so the items can point to their ids
        $sectionIds = $this->replaceSections($checklist, $data);

        // Step 2: items, with section_index resolved to section_id
        $this->replaceItems($checklist, $data, $sectionIds);
    }

    /**
     * Full replacement: sections matched by id are updated (they keep the id), the
     * missing ones are deleted (their items fall back to no section, nullOnDelete),
     * new ones are created; `position` is the index in the payload.
     *
     * @return array<int, int> section ids in payload order, so a `section_index` maps to an id
     */
    private function replaceSections(DocumentChecklist $checklist, SaveDocumentChecklistData $data): array
    {
        $existing = $checklist->sections()->get();
        $sectionIds = [];

        foreach ($data->sections as $position => $section) {
            $sectionIds[$position] = $section->id === null
                ? $checklist->sections()->create($section->attributes($position))->id
                : tap($existing->firstOrFail('id', $section->id))->update($section->attributes($position))->id;
        }

        $checklist->sections()->whereNotIn('id', $sectionIds)->delete();

        return $sectionIds;
    }

    /**
     * Full replacement: items matched by id are updated, the missing ones are
     * deleted, new ones are created; `position` is the index in the payload.
     *
     * @param  array<int, int>  $sectionIds
     */
    private function replaceItems(DocumentChecklist $checklist, SaveDocumentChecklistData $data, array $sectionIds): void
    {
        $existing = $checklist->items()->get();
        $keptIds = [];

        foreach ($data->items as $position => $item) {
            $attributes = $item->attributes($position, $item->sectionIndex === null ? null : $sectionIds[$item->sectionIndex]);

            if ($item->id === null) {
                $keptIds[] = $checklist->items()->create($attributes)->id;

                continue;
            }

            $existing->firstOrFail('id', $item->id)->update($attributes);
            $keptIds[] = $item->id;
        }

        $checklist->items()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes  extra attributes saved together with the status
     */
    private function markPending(DocumentChecklist $checklist, array $attributes = []): void
    {
        $checklist->forceFill([
            ...$attributes,
            'sync_status' => DocumentChecklistSyncStatus::Pending,
            'sync_error' => null,
        ])->save();
    }

    private function queueSync(DocumentChecklist $checklist): void
    {
        SyncDocumentChecklistJob::dispatch($checklist->id)->afterCommit();
    }
}
