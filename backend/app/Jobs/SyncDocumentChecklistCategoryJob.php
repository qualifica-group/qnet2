<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ArchivioSmartCallResult;
use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncStatus;
use App\Jobs\Concerns\PushesToArchivioSmart;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use App\Services\ArchivioSmart\DocumentChecklistCategoryPayloadBuilder;
use BackedEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pushes ONE checklist category to ArchivioSmart (spec 0410): PUT with the state
 * read when the job runs, or DELETE when the category is soft deleted. Outcome,
 * retry and logging rules are shared with the checklist job (PushesToArchivioSmart).
 */
class SyncDocumentChecklistCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PushesToArchivioSmart, Queueable;

    /** Seconds before an overlapped run is released back to the queue. */
    private const OVERLAP_RELEASE_SECONDS = 10;

    /** Lock safety net, above the HTTP timeouts, so a crashed worker never blocks a category. */
    private const OVERLAP_LOCK_SECONDS = 120;

    public int $tries;

    public function __construct(public readonly int $documentChecklistCategoryId)
    {
        $this->tries = (int) config('archivio-smart.tries');
    }

    /**
     * A run that overlaps another for the same category is released, not dropped:
     * the second dispatch carries the newer state and must still execute.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('category-'.$this->documentChecklistCategoryId))
                ->releaseAfter(self::OVERLAP_RELEASE_SECONDS)
                ->expireAfter(self::OVERLAP_LOCK_SECONDS),
        ];
    }

    public function handle(ArchivioSmartClient $client, DocumentChecklistCategoryPayloadBuilder $payloads): void
    {
        // Step 1: read the current state (soft-deleted rows included: they mean DELETE)
        $category = DocumentChecklistCategory::withTrashed()->find($this->documentChecklistCategoryId);
        if ($category === null) {
            return;
        }
        $operation = $category->trashed()
            ? DocumentChecklistSyncOperation::Delete
            : DocumentChecklistSyncOperation::Upsert;

        // Step 2: no connection, no call
        $setting = $this->configuredSetting($category, $operation);
        if ($setting === null) {
            return;
        }

        // Step 3: call ArchivioSmart and interpret the outcome
        $this->push($category, $operation, $setting, fn (ArchivioSmartSetting $setting): Response => $operation === DocumentChecklistSyncOperation::Delete
            ? $client->deleteCategory($setting, (string) $category->id)
            : $client->upsertCategory($setting, (string) $category->id, $payloads->build($category)));
    }

    /**
     * Safety net for worker-level kills (timeout, attempts exhausted while overlapped).
     */
    public function failed(?Throwable $exception): void
    {
        $category = DocumentChecklistCategory::withTrashed()->find($this->documentChecklistCategoryId);
        if ($category === null || $category->sync_status === DocumentChecklistSyncStatus::Synced) {
            return;
        }

        $this->markFailed($category, __('archivio_smart.job_generic_failure'));

        Log::error('ArchivioSmart category sync job killed before completion.', [
            'document_checklist_category_id' => $this->documentChecklistCategoryId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    /**
     * D-10: a 422 on the remote DELETE means the checklists of the category have
     * not been moved or removed in ArchivioSmart yet, so it is retried while
     * checklists are still waiting to be synced; otherwise it is final.
     */
    protected function onUnprocessable(Model $record, BackedEnum $operation, Response $response): ArchivioSmartCallResult
    {
        $waitingForChecklists = $operation === DocumentChecklistSyncOperation::Delete
            && DocumentChecklist::withTrashed()
                ->where('sync_status', DocumentChecklistSyncStatus::Pending)
                ->exists();

        $message = $this->validationMessage($response);

        return $waitingForChecklists
            ? $this->retryOrFail($record, $operation, 422, $message)
            : $this->failSync($record, $operation, 422, $message);
    }
}
