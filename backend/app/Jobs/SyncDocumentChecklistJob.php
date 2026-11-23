<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ArchivioSmartCallResult;
use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\Concerns\PushesToArchivioSmart;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use App\Services\ArchivioSmart\DocumentChecklistCategoryPayloadBuilder;
use App\Services\ArchivioSmart\DocumentChecklistPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes ONE checklist to ArchivioSmart (spec 0403, D-6): PUT with the state read
 * when the job runs, or DELETE when the checklist is soft deleted. Transient
 * failures (network, 5xx, 429) are retried with backoff; 401/409/422 and other
 * 4xx are final. Every attempt writes a sync log row that never holds the token,
 * the headers or the body.
 */
class SyncDocumentChecklistJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PushesToArchivioSmart, Queueable;

    /** Seconds before an overlapped run is released back to the queue. */
    private const OVERLAP_RELEASE_SECONDS = 10;

    /** Lock safety net, above the HTTP timeouts, so a crashed worker never blocks a checklist. */
    private const OVERLAP_LOCK_SECONDS = 120;

    public int $tries;

    public function __construct(public readonly int $documentChecklistId)
    {
        $this->tries = (int) config('archivio-smart.tries');
    }

    /**
     * A run that overlaps another for the same checklist is released, not dropped:
     * the second dispatch carries the newer state and must still execute.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->documentChecklistId))
                ->releaseAfter(self::OVERLAP_RELEASE_SECONDS)
                ->expireAfter(self::OVERLAP_LOCK_SECONDS),
        ];
    }

    public function handle(
        ArchivioSmartClient $client,
        DocumentChecklistPayloadBuilder $payloads,
        DocumentChecklistCategoryPayloadBuilder $categoryPayloads,
    ): void {
        // Step 1: read the current state (soft-deleted rows included: they mean DELETE)
        $checklist = DocumentChecklist::withTrashed()->with(['items', 'sections', 'category'])->find($this->documentChecklistId);
        if ($checklist === null) {
            return;
        }
        $operation = $checklist->trashed()
            ? DocumentChecklistSyncOperation::Delete
            : DocumentChecklistSyncOperation::Upsert;

        // Step 2: no connection, no call
        $setting = $this->configuredSetting($checklist, $operation);
        if ($setting === null) {
            return;
        }

        // Step 3: ArchivioSmart must know the category before the checklist that uses it (D-7)
        if ($operation === DocumentChecklistSyncOperation::Upsert && ! $this->categoryIsReady($checklist, $setting, $client, $categoryPayloads)) {
            return;
        }

        // Step 4: call ArchivioSmart and interpret the outcome
        $this->push($checklist, $operation, $setting, fn (ArchivioSmartSetting $setting): Response => $operation === DocumentChecklistSyncOperation::Delete
            ? $client->deleteChecklist($setting, (string) $checklist->id)
            : $client->upsertChecklist($setting, (string) $checklist->id, $payloads->build($checklist)));
    }

    /**
     * Safety net for worker-level kills (timeout, attempts exhausted while overlapped).
     */
    public function failed(?Throwable $exception): void
    {
        $checklist = DocumentChecklist::withTrashed()->find($this->documentChecklistId);
        if ($checklist === null || $checklist->sync_status === DocumentChecklistSyncStatus::Synced) {
            return;
        }

        $this->markFailed($checklist, __('archivio_smart.job_generic_failure'));

        Log::error('ArchivioSmart checklist sync job killed before completion.', [
            'document_checklist_id' => $this->documentChecklistId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    /**
     * Sends the category first when it is not synced yet (idempotent PUT, logged on
     * the category). Returns false when the checklist must stop here: the job has
     * been released (transient error) or the checklist has been failed (final error).
     */
    private function categoryIsReady(
        DocumentChecklist $checklist,
        ArchivioSmartSetting $setting,
        ArchivioSmartClient $client,
        DocumentChecklistCategoryPayloadBuilder $categoryPayloads,
    ): bool {
        $category = $checklist->category;
        if ($category === null || $category->sync_status === DocumentChecklistSyncStatus::Synced) {
            return true;
        }

        $result = $this->push(
            $category,
            DocumentChecklistSyncOperation::Upsert,
            $setting,
            fn (ArchivioSmartSetting $setting): Response => $client->upsertCategory($setting, (string) $category->id, $categoryPayloads->build($category)),
        );

        if ($result === ArchivioSmartCallResult::Failed) {
            $this->failSync(
                $checklist,
                DocumentChecklistSyncOperation::Upsert,
                null,
                __('archivio_smart.category_not_synced', ['reason' => $this->failureMessage]),
            );
        }

        return $result === ArchivioSmartCallResult::Succeeded;
    }
}
