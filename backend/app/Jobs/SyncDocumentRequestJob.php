<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ArchivioSmartCallResult;
use App\Enums\DocumentChecklistSyncOutcome;
use App\Enums\DocumentRequestDeliveryStatus;
use App\Enums\DocumentRequestOperation;
use App\Jobs\Concerns\PushesToArchivioSmart;
use App\Models\ArchivioSmartSetting;
use App\Models\DocumentRequest;
use App\Services\ArchivioSmart\ArchivioSmartClient;
use App\Services\DocumentRequests\DocumentRequestPayloadBuilder;
use App\Services\DocumentRequests\DocumentRequestRemoteWriter;
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
 * Runs ONE outbound operation of a document request against ArchivioSmart
 * (spec 0428, D-4): upsert, remind, cancel, refresh or, from spec 0429, approve,
 * reject and complete. Operations of the same
 * request run one at a time. Transient failures (network, 5xx, 429) are retried
 * with backoff; 401/409/422 and other 4xx are final. The response replaces the
 * local snapshot. Log rows never hold the token, the body or personal data.
 */
class SyncDocumentRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use PushesToArchivioSmart {
        interpret as protected interpretResponse;
    }

    /** Seconds before an overlapped run is released back to the queue. */
    private const OVERLAP_RELEASE_SECONDS = 10;

    /** Lock safety net, above the HTTP timeouts, so a crashed worker never blocks a request. */
    private const OVERLAP_LOCK_SECONDS = 120;

    /** @var array<int, DocumentRequestOperation> */
    private const array REVIEW_OPERATIONS = [
        DocumentRequestOperation::Approve,
        DocumentRequestOperation::Reject,
        DocumentRequestOperation::Complete,
    ];

    public int $tries;

    /** The last remote response, read by succeed() and by the 409 message. */
    private ?Response $response = null;

    /**
     * @param  array{item_id?: int, note?: string|null, reviewer_name?: string}  $arguments  of approve and reject; the note and the name never reach a log
     */
    public function __construct(
        public readonly int $documentRequestId,
        public readonly DocumentRequestOperation $operation,
        public readonly bool $send = true,
        public readonly array $arguments = [],
    ) {
        $this->tries = (int) config('archivio-smart.tries');
    }

    /**
     * A run overlapping another for the same request is released, not dropped:
     * every operation must still execute, in dispatch order.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->documentRequestId))
                ->releaseAfter(self::OVERLAP_RELEASE_SECONDS)
                ->expireAfter(self::OVERLAP_LOCK_SECONDS),
        ];
    }

    public function handle(
        ArchivioSmartClient $client,
        DocumentRequestPayloadBuilder $payloads,
    ): void {
        // Step 1: read the current state
        $request = DocumentRequest::query()->find($this->documentRequestId);
        if ($request === null) {
            return;
        }

        // Step 2: no connection, no call
        $setting = $this->configuredSetting($request, $this->operation);
        if ($setting === null) {
            return;
        }

        // Step 3: call ArchivioSmart and interpret the outcome
        $externalId = (string) $request->id;
        $this->push($request, $this->operation, $setting, function (ArchivioSmartSetting $setting) use ($client, $payloads, $request, $externalId): Response {
            return $this->response = match ($this->operation) {
                DocumentRequestOperation::Upsert => $client->upsertRequest($setting, $externalId, $payloads->build($request, $this->send)),
                DocumentRequestOperation::Remind => $client->remindRequest($setting, $externalId),
                DocumentRequestOperation::Cancel => $client->cancelRequest($setting, $externalId),
                DocumentRequestOperation::Refresh => $client->getRequest($setting, $externalId),
                DocumentRequestOperation::Approve, DocumentRequestOperation::Reject => $client->reviewItem(
                    $setting,
                    $externalId,
                    (int) $this->arguments['item_id'],
                    $this->operation->value,
                    $this->arguments['note'] ?? null,
                    (string) $this->arguments['reviewer_name'],
                ),
                DocumentRequestOperation::Complete => $client->completeRequest($setting, $externalId),
            };
        });
    }

    /**
     * The review and complete endpoints may not be released by ArchivioSmart yet
     * (spec 0429, D-6): a 404 on them says so instead of a generic message.
     */
    protected function interpret(Model $record, BackedEnum $operation, Response $response): ArchivioSmartCallResult
    {
        if ($response->status() === 404 && in_array($this->operation, self::REVIEW_OPERATIONS, true)) {
            return $this->failSync($record, $operation, 404, __('archivio_smart.request_action_unavailable'));
        }

        return $this->interpretResponse($record, $operation, $response);
    }

    /**
     * Safety net for worker-level kills (timeout, attempts exhausted while overlapped).
     */
    public function failed(?Throwable $exception): void
    {
        $request = DocumentRequest::query()->find($this->documentRequestId);
        if ($request === null || $request->delivery_status !== DocumentRequestDeliveryStatus::Pending) {
            return;
        }

        $this->markFailed($request, __('archivio_smart.job_generic_failure'));

        Log::error('ArchivioSmart document request job killed before completion.', [
            'document_request_id' => $this->documentRequestId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }

    /**
     * The response replaces the snapshot. The request stays "pending" while a
     * later operation (another job) is still waiting for its turn.
     */
    protected function succeed(Model $record, BackedEnum $operation, int $status): ArchivioSmartCallResult
    {
        /** @var DocumentRequest $record */
        $data = $this->response?->json('data');
        if (is_array($data)) {
            app(DocumentRequestRemoteWriter::class)->apply($record, $data);
        }

        // Read again: another operation may have been requested while this call was in flight.
        $latest = DocumentRequest::query()->whereKey($record->id)->value('pending_operation');
        $isLast = $latest === null || $latest === $this->operation;
        activity()->withoutLogs(fn () => $record->forceFill($isLast ? [
            'delivery_status' => DocumentRequestDeliveryStatus::Delivered,
            'pending_operation' => null,
            'delivery_error' => null,
        ] : [])->save());
        $this->log($record, $operation, DocumentChecklistSyncOutcome::Success, $status, null);

        return ArchivioSmartCallResult::Succeeded;
    }

    protected function markFailed(Model $record, string $message): void
    {
        activity()->withoutLogs(fn () => $record->forceFill([
            'delivery_status' => DocumentRequestDeliveryStatus::Failed,
            'delivery_error' => $this->truncate($message),
        ])->save());
    }

    /**
     * ArchivioSmart explains a conflict in its own `message`.
     */
    protected function conflictMessage(Model $record): string
    {
        $remote = $this->response?->json('message');

        return is_string($remote) && $remote !== '' ? $remote : __('archivio_smart.request_conflict');
    }
}
