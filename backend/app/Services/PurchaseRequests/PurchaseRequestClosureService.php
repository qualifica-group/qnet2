<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closure of a purchase request (spec 0208, D-7): automatic once every line is
 * in a terminal state, or by hand. Closing with lines still open is "forced"
 * and needs a reason. A closed request is never reopened.
 */
class PurchaseRequestClosureService
{
    /**
     * What the close dialog needs to know (`can_close` is the caller's concern
     * for permissions: here it only says whether the request is still open).
     *
     * @return array{can_close: bool, is_forced: bool, non_terminal_count: int, line_status_counts: array<string, int>}
     */
    public function info(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing('lines:id,purchase_request_id,status');
        $nonTerminal = $this->nonTerminalCount($purchaseRequest);

        return [
            'can_close' => ! $purchaseRequest->isClosed(),
            'is_forced' => $nonTerminal > 0,
            'non_terminal_count' => $nonTerminal,
            'line_status_counts' => $purchaseRequest->lineStatusCounts(),
        ];
    }

    /**
     * Manual closure (409 when already closed, 422 `reason` when forced without one).
     */
    public function close(PurchaseRequest $purchaseRequest, User $actor, ?string $reason): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $actor, $reason): PurchaseRequest {
            // Step 1: serialize on the request and refuse a closed one
            $locked = PurchaseRequest::query()->whereKey($purchaseRequest->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                abort(409, 'This purchase request is already closed.');
            }

            // Step 2: a forced closure (lines not terminal) must be justified
            $reason = filled($reason) ? trim($reason) : null;
            $locked->load('lines:id,purchase_request_id,status');

            if ($this->nonTerminalCount($locked) > 0 && $reason === null) {
                throw ValidationException::withMessages(['reason' => ['A reason is required to close a purchase request with open lines.']]);
            }

            // Step 3: close
            $this->markClosed($locked, $actor, $reason);

            return $locked;
        });
    }

    /**
     * Automatic closure after a status change: closes the (already locked)
     * request when no line is left to work on. Returns whether it closed.
     */
    public function closeIfComplete(PurchaseRequest $purchaseRequest, User $actor): bool
    {
        $purchaseRequest->load('lines:id,purchase_request_id,status');

        if ($purchaseRequest->isClosed() || $this->nonTerminalCount($purchaseRequest) > 0) {
            return false;
        }

        $this->markClosed($purchaseRequest, $actor, null);

        return true;
    }

    private function markClosed(PurchaseRequest $purchaseRequest, User $actor, ?string $reason): void
    {
        $purchaseRequest->forceFill([
            'status' => PurchaseRequestStatus::Closed,
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'close_reason' => $reason,
        ])->save();
    }

    private function nonTerminalCount(PurchaseRequest $purchaseRequest): int
    {
        return $purchaseRequest->lines->reject(
            fn (PurchaseRequestLine $line): bool => $line->status->isTerminal(),
        )->count();
    }
}
