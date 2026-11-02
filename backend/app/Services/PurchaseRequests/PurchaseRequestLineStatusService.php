<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Status changes of purchase request lines, single or mass (spec 0208, D-8/D-9).
 * All or nothing: the rows are locked, every line is checked against the actor's
 * capabilities and the transition matrix, and only then is anything written.
 * Every change leaves a log row; the touched requests are then given a chance to
 * close by themselves (D-7).
 */
class PurchaseRequestLineStatusService
{
    /** Statuses that record who approved (or rejected) the line and when. */
    private const array APPROVAL_STATUSES = [PurchaseRequestLineStatus::Approved, PurchaseRequestLineStatus::Rejected];

    /** Statuses owned by the purchase order once the line is linked to an order line. */
    private const array ORDER_DRIVEN_STATUSES = [PurchaseRequestLineStatus::Ordered, PurchaseRequestLineStatus::Received];

    public function __construct(
        private readonly PurchaseRequestCapabilityResolver $capabilities,
        private readonly PurchaseRequestClosureService $closure,
        private readonly PurchaseRequestLineLocker $locker,
    ) {}

    /**
     * @param  array<int, int>  $lineIds  in request order (the 422 keys follow it)
     * @param  array<int, int>  $suppliers  line id => supplier id, only with `ordered` (spec 0209, D-2)
     * @return array{updated_count: int, closed_purchase_request_ids: array<int, int>}
     */
    public function change(User $actor, array $lineIds, PurchaseRequestLineStatus $to, ?string $reason, array $suppliers = []): array
    {
        return DB::transaction(function () use ($actor, $lineIds, $to, $reason, $suppliers): array {
            // Step 1: lock the lines and their requests
            $lines = $this->locker->lines($lineIds)->load('purchaseOrderLine:id,purchase_request_line_id');
            $requests = $this->locker->requests($lines);

            // Step 2: refuse the whole change if any line is out of reach (403), closed (409) or not allowed (422)
            $this->assertAllowed($actor, $lineIds, $lines, $requests, $to);

            // Step 3: ordering needs a supplier on every line (existing or supplied)
            $this->assertSuppliersPresent($lineIds, $lines, $to, $suppliers);

            // Step 4: apply and log, one group id per mass change
            $groupId = count($lineIds) > 1 ? (string) Str::uuid() : null;

            foreach ($lines as $line) {
                if (isset($suppliers[$line->id])) {
                    $line->supplier_id = $suppliers[$line->id];
                }

                $this->apply($line, $actor, $to, $reason, $groupId);
            }

            // Step 5: close the requests that have nothing left to work on
            $closed = $requests->filter(fn (PurchaseRequest $request): bool => $this->closure->closeIfComplete($request, $actor));

            return [
                'updated_count' => $lines->count(),
                'closed_purchase_request_ids' => $closed->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ];
        });
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @param  array<int, int>  $suppliers
     */
    private function assertSuppliersPresent(array $lineIds, Collection $lines, PurchaseRequestLineStatus $to, array $suppliers): void
    {
        if ($to !== PurchaseRequestLineStatus::Ordered) {
            return;
        }

        $errors = [];

        foreach ($lineIds as $index => $id) {
            if ($lines->get($id)->supplier_id === null && ! isset($suppliers[$id])) {
                $errors["line_ids.{$index}"] = [__('purchase_request_lines.supplier_required')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @param  Collection<int, PurchaseRequest>  $requests
     */
    private function assertAllowed(User $actor, array $lineIds, Collection $lines, Collection $requests, PurchaseRequestLineStatus $to): void
    {
        $ordered = array_map(fn (int $id): PurchaseRequestLine => $lines->get($id) ?? abort(404), $lineIds);

        foreach ($ordered as $line) {
            if ($this->capabilities->capabilities($actor, $requests->get($line->purchase_request_id)) === []) {
                throw new AuthorizationException(__('You do not hold a role on this purchase request that allows this change.'));
            }
        }

        foreach ($ordered as $line) {
            if ($requests->get($line->purchase_request_id)->isClosed()) {
                abort(409, 'This purchase request is closed and can no longer be changed.');
            }
        }

        $errors = [];

        foreach ($ordered as $index => $line) {
            if (! in_array($to, $this->capabilities->transitions($actor, $requests->get($line->purchase_request_id), $line), true)) {
                $errors["line_ids.{$index}"] = [__('purchase_request_lines.status_change_not_allowed')];
            } elseif ($this->isDrivenByOrder($line, $to)) {
                $errors["line_ids.{$index}"] = [__('purchase_request_lines.line_linked_to_order')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** A line linked to an order line leaves `ordered`/`received` only through the order (spec 0245, D-8). */
    private function isDrivenByOrder(PurchaseRequestLine $line, PurchaseRequestLineStatus $to): bool
    {
        return in_array($line->status, self::ORDER_DRIVEN_STATUSES, true)
            && $line->status !== $to
            && $line->purchaseOrderLine !== null;
    }

    private function apply(PurchaseRequestLine $line, User $actor, PurchaseRequestLineStatus $to, ?string $reason, ?string $groupId): void
    {
        $from = $line->status;
        $line->status = $to;

        if (in_array($to, self::APPROVAL_STATUSES, true)) {
            $line->approved_by = $actor->id;
            $line->approved_at = now();
        } elseif ($to === PurchaseRequestLineStatus::PendingApproval) {
            $line->approved_by = null;
            $line->approved_at = null;
        }

        $line->save();

        PurchaseRequestLineStatusLog::query()->create([
            'purchase_request_line_id' => $line->id,
            'user_id' => $actor->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => filled($reason) ? trim($reason) : null,
            'bulk_group_id' => $groupId,
        ]);
    }
}
