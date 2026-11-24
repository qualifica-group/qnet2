<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Enums\ArchivioSmartWebhookEventStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Models\ArchivioSmartWebhookEvent;
use App\Models\DocumentRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applies one ArchivioSmart webhook event to the local document request (spec 0428, D-6).
 * Webhooks only touch the state of the item they name and the request fields:
 * the snapshot is replaced as a whole only by a response (D-3). An event older
 * than the last remote state never rolls it back; for an item event it asks for a refresh.
 */
class ArchivioSmartWebhookApplier
{
    private const string EVENT_UPLOADED = 'document.uploaded';

    private const string EVENT_VALIDATED = 'document.validated';

    private const string EVENT_COMPLETED = 'request.completed';

    private const string EVENT_EXPIRED = 'request.expired';

    private const string ITEM_STATUS_UPLOADED = 'uploaded';

    /** Events that are only recorded. */
    private const array RECORD_ONLY = ['ping', 'request.created'];

    private const string DOCUMENT_EVENT_PREFIX = 'document.';

    public function __construct(private readonly DocumentRequestService $requests) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(ArchivioSmartWebhookEvent $event, array $payload): ArchivioSmartWebhookEventStatus
    {
        // Step 1: ping and creation echoes change nothing
        if (in_array($event->event, self::RECORD_ONLY, true)) {
            return ArchivioSmartWebhookEventStatus::Processed;
        }

        // Step 2: find the request; an unknown one is ignored
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $request = $this->findRequest($data);
        if ($request === null) {
            return ArchivioSmartWebhookEventStatus::Ignored;
        }
        $event->forceFill(['document_request_id' => $request->id])->save();

        // Step 3: apply under a row lock, so two events of one request never interleave
        DB::transaction(function () use ($event, $request, $data): void {
            $locked = DocumentRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->applyTo($locked, $event, $data);
        });

        return ArchivioSmartWebhookEventStatus::Processed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findRequest(array $data): ?DocumentRequest
    {
        $externalId = is_array($data['request'] ?? null) ? ($data['request']['external_id'] ?? null) : null;

        return is_scalar($externalId) && ctype_digit((string) $externalId)
            ? DocumentRequest::query()->find((int) $externalId)
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyTo(DocumentRequest $request, ArchivioSmartWebhookEvent $event, array $data): void
    {
        $occurredAt = $event->occurred_at ?? now();
        $isStale = $request->remote_updated_at !== null && $occurredAt->lt($request->remote_updated_at);
        $isItemEvent = str_starts_with($event->event, self::DOCUMENT_EVENT_PREFIX);

        $changes = [];
        if (! $isStale) {
            $changes = $this->requestChanges($event->event, $data, $occurredAt);
            if ($isItemEvent) {
                $this->applyItem($request, $event->event, $data, $occurredAt, $changes);
            }
        }

        $changes['last_event_at'] = $request->last_event_at === null || $occurredAt->gt($request->last_event_at) ? $occurredAt : $request->last_event_at;
        activity()->withoutLogs(fn () => $request->forceFill($changes)->save());

        // A document event carries neither the request counters nor the item's file
        // list (nor a stale or unknown item): the full request is re-read from ArchivioSmart.
        if ($isItemEvent) {
            $this->requests->request($request, DocumentRequestOperation::Refresh);
        }
    }

    /**
     * Request fields carried by the event.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requestChanges(string $event, array $data, CarbonInterface $occurredAt): array
    {
        $remote = is_array($data['request'] ?? null) ? $data['request'] : [];
        $changes = ['remote_updated_at' => $occurredAt];

        $status = DocumentRequestStatus::tryFrom((string) ($remote['status'] ?? ''));
        if ($event === self::EVENT_COMPLETED) {
            $status = DocumentRequestStatus::Completed;
            $changes['completed_at'] = $occurredAt;
        } elseif ($event === self::EVENT_EXPIRED) {
            $status = DocumentRequestStatus::Expired;
            $changes['expired_at'] = $occurredAt;
        }
        if ($status !== null) {
            $changes['status'] = $status;
        }

        return $changes;
    }

    /**
     * Sets the state of the named item in the snapshot right away, so it shows
     * even before the refresh answers. An item missing from the snapshot is left
     * to the refresh.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changes  receives the new snapshot
     */
    private function applyItem(DocumentRequest $request, string $event, array $data, CarbonInterface $occurredAt, array &$changes): void
    {
        $itemId = is_array($data['item'] ?? null) ? ($data['item']['id'] ?? null) : null;
        $snapshot = $request->snapshot ?? [];
        $items = $snapshot['items'] ?? [];

        $index = collect($items)->search(static fn (array $item): bool => is_scalar($itemId) && (string) ($item['id'] ?? '') === (string) $itemId);
        if ($index === false) {
            return;
        }

        $validation = is_array($data['validation'] ?? null) ? $data['validation'] : [];
        if ($event === self::EVENT_UPLOADED) {
            $items[$index]['status'] = self::ITEM_STATUS_UPLOADED;
        } elseif ($event === self::EVENT_VALIDATED) {
            $items[$index]['status'] = $validation['item_status'] ?? $items[$index]['status'] ?? null;
            $items[$index]['status_reason'] = $validation['reason_for_customer'] ?? null;
            $items[$index]['latest_validation'] = [
                'outcome' => $validation['outcome'] ?? $validation['item_status'] ?? null,
                'reason_for_customer' => $validation['reason_for_customer'] ?? null,
                'decided_by' => $validation['decided_by'] ?? null,
                'created_at' => $validation['created_at'] ?? $occurredAt->toIso8601String(),
            ];
        }

        $changes['snapshot'] = [...$snapshot, 'items' => $items];
    }
}
