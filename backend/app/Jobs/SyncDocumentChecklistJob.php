<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DocumentChecklistSyncOperation;
use App\Enums\DocumentChecklistSyncOutcome;
use App\Enums\DocumentChecklistSyncStatus;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentChecklist;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use App\Services\ArchivioSmart\DocumentChecklistPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    use Dispatchable, InteractsWithQueue, Queueable;

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

    public function handle(ArchivioSmartClient $client, DocumentChecklistPayloadBuilder $payloads): void
    {
        // Step 1: read the current state (soft-deleted rows included: they mean DELETE)
        $checklist = DocumentChecklist::withTrashed()->with('items')->find($this->documentChecklistId);
        if ($checklist === null) {
            return;
        }
        $operation = $checklist->trashed()
            ? DocumentChecklistSyncOperation::Delete
            : DocumentChecklistSyncOperation::Upsert;

        // Step 2: no connection, no call
        $setting = ArchivioSmartSetting::current();
        if ($setting === null || ! $setting->isConfigured()) {
            $this->failSync($checklist, $operation, null, __('archivio_smart.not_configured'));

            return;
        }

        // Step 3: call ArchivioSmart
        try {
            $externalId = (string) $checklist->id;
            $response = $operation === DocumentChecklistSyncOperation::Delete
                ? $client->deleteChecklist($setting, $externalId)
                : $client->upsertChecklist($setting, $externalId, $payloads->build($checklist));
        } catch (ConnectionException) {
            $this->retryOrFail($checklist, $operation, null, __('archivio_smart.network_error'));

            return;
        }

        // Step 4: interpret the outcome
        $this->interpret($checklist, $operation, $response);
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

    private function interpret(DocumentChecklist $checklist, DocumentChecklistSyncOperation $operation, Response $response): void
    {
        $status = $response->status();

        match (true) {
            in_array($status, [200, 201, 204], true) => $this->succeed($checklist, $operation, $status),
            $status === 422 => $this->failSync($checklist, $operation, $status, $this->validationMessage($response)),
            $status === 401 => $this->failSync($checklist, $operation, $status, __('archivio_smart.unauthorized')),
            $status === 409 => $this->failSync($checklist, $operation, $status, __('archivio_smart.conflict')),
            $status === 429 => $this->releaseAfterRateLimit($checklist, $operation, $response),
            $status >= 500 => $this->retryOrFail($checklist, $operation, $status, __('archivio_smart.server_error', ['status' => $status])),
            default => $this->failSync($checklist, $operation, $status, __('archivio_smart.unexpected_status', ['status' => $status])),
        };
    }

    private function succeed(DocumentChecklist $checklist, DocumentChecklistSyncOperation $operation, int $status): void
    {
        $attributes = [
            'sync_status' => DocumentChecklistSyncStatus::Synced,
            'sync_error' => null,
            'synced_at' => now(),
        ];
        if ($operation === DocumentChecklistSyncOperation::Upsert && $checklist->first_synced_at === null) {
            $attributes['first_synced_at'] = now();
        }

        activity()->withoutLogs(fn () => $checklist->update($attributes));
        $this->log($checklist, $operation, DocumentChecklistSyncOutcome::Success, $status, null);
    }

    private function failSync(DocumentChecklist $checklist, DocumentChecklistSyncOperation $operation, ?int $status, string $message): void
    {
        $this->markFailed($checklist, $message);
        $this->log($checklist, $operation, DocumentChecklistSyncOutcome::Failed, $status, $message);
    }

    private function retryOrFail(DocumentChecklist $checklist, DocumentChecklistSyncOperation $operation, ?int $status, string $message): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->failSync($checklist, $operation, $status, $message);

            return;
        }

        $this->log($checklist, $operation, DocumentChecklistSyncOutcome::Retrying, $status, $message);
        $this->release($this->backoffSeconds());
    }

    private function releaseAfterRateLimit(DocumentChecklist $checklist, DocumentChecklistSyncOperation $operation, Response $response): void
    {
        $retryAfter = $response->header('Retry-After');
        $delay = ctype_digit($retryAfter) ? (int) $retryAfter : (int) config('archivio-smart.retry_after_fallback');

        $this->log($checklist, $operation, DocumentChecklistSyncOutcome::Retrying, 429, null);
        $this->release($delay);
    }

    private function backoffSeconds(): int
    {
        /** @var array<int, int> $backoff */
        $backoff = config('archivio-smart.backoff');

        return (int) ($backoff[$this->attempts() - 1] ?? end($backoff));
    }

    private function validationMessage(Response $response): string
    {
        $errors = $response->json('errors');
        $messages = is_array($errors) ? collect($errors)->flatten()->filter(fn (mixed $message): bool => is_string($message))->implode(' ') : '';

        return $messages !== '' ? $messages : __('archivio_smart.validation_failed');
    }

    private function markFailed(DocumentChecklist $checklist, string $message): void
    {
        activity()->withoutLogs(fn () => $checklist->update([
            'sync_status' => DocumentChecklistSyncStatus::Failed,
            'sync_error' => $this->truncate($message),
        ]));
    }

    private function log(
        DocumentChecklist $checklist,
        DocumentChecklistSyncOperation $operation,
        DocumentChecklistSyncOutcome $outcome,
        ?int $status,
        ?string $message,
    ): void {
        $checklist->syncLogs()->create([
            'operation' => $operation,
            'outcome' => $outcome,
            'http_status' => $status,
            'attempt' => $this->attempts(),
            'message' => $message === null ? null : $this->truncate($message),
        ]);
    }

    private function truncate(string $message): string
    {
        return Str::limit($message, (int) config('archivio-smart.error_message_max_length'), '');
    }
}
