<?php

declare(strict_types=1);

namespace App\Services\DocumentRequests;

use App\Enums\DocumentChecklistSyncStatus;
use App\Enums\DocumentRequestOperation;
use App\Enums\DocumentRequestStatus;
use App\Models\DocumentChecklist;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrected checklist rules on open requests (spec 0432): copy the rules into a
 * request, revalidate items with the AI, stop a pending validation. Every call
 * goes through the queue like the other operations; who asked goes to the activity log (D-6).
 */
class DocumentRequestRulesService
{
    /** Item states with a file still waiting for the AI (D-5). */
    private const array STOPPABLE_ITEM_STATUSES = ['uploaded', 'validating'];

    public function __construct(private readonly DocumentRequestService $requests) {}

    public function refreshRules(DocumentRequest $request, User $actor): DocumentRequest
    {
        if (! $request->acceptsRulesRefresh()) {
            $this->refuse('status', __('archivio_smart.request_rules_not_refreshable'));
        }

        return DB::transaction(function () use ($request, $actor): DocumentRequest {
            $this->audit($request, $actor, 'rules_refresh_requested');

            return $this->requests->request($request, DocumentRequestOperation::RefreshRules);
        });
    }

    public function openRequestsCount(DocumentChecklist $checklist): int
    {
        return $this->openRequestsOf($checklist)->count();
    }

    /**
     * Refreshes every open request of the checklist; ArchivioSmart must already hold the corrected checklist (D-1).
     */
    public function refreshOpenRequests(DocumentChecklist $checklist, User $actor): int
    {
        if ($checklist->sync_status !== DocumentChecklistSyncStatus::Synced) {
            $this->refuse('checklist', __('archivio_smart.checklist_not_synced'));
        }

        return DB::transaction(function () use ($checklist, $actor): int {
            $requests = $this->openRequestsOf($checklist)->get();
            foreach ($requests as $request) {
                $this->audit($request, $actor, 'rules_refresh_requested');
                $this->requests->request($request, DocumentRequestOperation::RefreshRules);
            }

            return $requests->count();
        });
    }

    /**
     * One call per item (D-4). The items leave the list of the ones changed by the last refresh.
     *
     * @param  array<int, int>  $itemIds
     */
    public function revalidate(DocumentRequest $request, array $itemIds, User $actor): DocumentRequest
    {
        // Step 1: the request must be open and every item must hold a file
        $this->assertReviewable($request);
        foreach ($itemIds as $itemId) {
            if (empty($this->itemOf($request, $itemId)['documents'])) {
                $this->refuse('document', __('archivio_smart.request_item_without_document'));
            }
        }

        // Step 2: record who asked and queue one call per item
        return DB::transaction(function () use ($request, $itemIds, $actor): DocumentRequest {
            $request->rules_updated_item_ids = array_values(array_diff($request->rules_updated_item_ids ?? [], $itemIds));
            $this->audit($request, $actor, 'revalidation_requested', ['item_ids' => $itemIds]);
            foreach ($itemIds as $itemId) {
                $this->requests->request($request, DocumentRequestOperation::Revalidate, true, ['item_id' => $itemId]);
            }

            return $request;
        });
    }

    public function stopValidation(DocumentRequest $request, int $itemId, User $actor): DocumentRequest
    {
        // Step 1: the item must have a file waiting for the AI
        $this->assertReviewable($request);
        if (! in_array($this->itemOf($request, $itemId)['status'] ?? null, self::STOPPABLE_ITEM_STATUSES, true)) {
            $this->refuse('document', __('archivio_smart.request_item_not_stoppable'));
        }

        // Step 2: record who asked and queue the call, with the name shown in ArchivioSmart
        return DB::transaction(function () use ($request, $itemId, $actor): DocumentRequest {
            $this->audit($request, $actor, 'validation_stop_requested', ['item_id' => $itemId]);

            return $this->requests->request($request, DocumentRequestOperation::StopValidation, true, [
                'item_id' => $itemId,
                'reviewer_name' => $actor->name,
            ]);
        });
    }

    /**
     * @return Builder<DocumentRequest>
     */
    private function openRequestsOf(DocumentChecklist $checklist): Builder
    {
        return DocumentRequest::query()
            ->where('document_checklist_id', $checklist->id)
            ->whereIn('status', array_map(static fn (DocumentRequestStatus $status): string => $status->value, DocumentRequestStatus::open()));
    }

    /**
     * @return array<string, mixed>
     */
    private function itemOf(DocumentRequest $request, int $itemId): array
    {
        return $request->snapshotItem($itemId) ?? $this->refuse('item', __('archivio_smart.request_item_not_found'));
    }

    private function assertReviewable(DocumentRequest $request): void
    {
        if (! $request->isReviewable()) {
            $this->refuse('status', __('archivio_smart.request_not_reviewable'));
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(DocumentRequest $request, User $actor, string $event, array $properties = []): void
    {
        activity()
            ->performedOn($request)
            ->causedBy($actor)
            ->event($event)
            ->withProperties($properties)
            ->useLog($request->getTable())
            ->log($event);
    }

    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
