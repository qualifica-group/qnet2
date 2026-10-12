<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderMergeAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderMergeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Merge of purchase orders (spec 0210, D-19): lines of other orders of the same
 * supplier and company are MOVED (same id) to the tail of the destination, the
 * totals of both orders are recomputed, a source left empty is deleted and
 * every source leaves a log with a snapshot taken before the move. All or
 * nothing, with the involved orders locked in id order. Deleting the source
 * here never goes through PurchaseOrderService::delete: its lines are gone, so
 * the request lines they carry stay linked (to the destination).
 */
class PurchaseOrderMergeService
{
    /** Header columns kept in the snapshot of a source. */
    private const array SNAPSHOT_HEADER_KEYS = [
        'id', 'number', 'year', 'status', 'supplier_id', 'company_id', 'company_site_id', 'operational_site_id',
        'order_date', 'description', 'general_notes', 'payment_method_id', 'customer_id', 'work_order_id',
        'quote_id', 'opportunity_id', 'sector_notes', 'taxable_total', 'vat_total', 'grand_total',
    ];

    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly PurchaseOrderAmountCalculator $amounts,
        private readonly PurchaseRequestLineLinker $linker,
    ) {}

    /**
     * Orders of the same supplier and company that can be merged into $order,
     * with their lines (409 when $order itself is not editable).
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function candidates(PurchaseOrder $order): Collection
    {
        $this->orders->assertEditable($order);

        return PurchaseOrder::query()
            ->where('supplier_id', $order->supplier_id)
            ->where('company_id', $order->company_id)
            ->whereIn('status', array_map(fn (PurchaseOrderStatus $status): string => $status->value, $this->editableStatuses()))
            ->whereKeyNot($order->getKey())
            ->with('lines:id,purchase_order_id,position,description,quantity,unit_price,taxable_amount,total_amount')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Merges where $order is the destination, most recent first.
     *
     * @return Collection<int, PurchaseOrderMergeLog>
     */
    public function logs(PurchaseOrder $order): Collection
    {
        return $order->mergeLogs()->with('performedBy:id,name')->get();
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array{purchase_order: PurchaseOrder, deleted_purchase_order_ids: array<int, int>}
     */
    public function merge(PurchaseOrder $destination, array $lineIds, User $actor): array
    {
        return DB::transaction(function () use ($destination, $lineIds, $actor): array {
            // Step 1: lock the destination and every source, in id order
            $orders = $this->lockOrders($destination, $lineIds);
            $locked = $orders->get($destination->getKey());
            $lines = $this->lockLines($lineIds, $orders);

            // Step 2: refuse the whole merge on a locked state (409) or an incompatible line (422)
            $sources = $orders->except($locked->getKey());
            $this->assertEditable($locked, $sources);
            $this->assertCompatible($locked, $lineIds, $lines, $orders);

            // Step 3: move the lines source by source, leaving a log each
            $deleted = [];
            $position = (int) PurchaseOrderLine::query()->where('purchase_order_id', $locked->id)->max('position');

            foreach ($sources as $source) {
                $moving = $lines->where('purchase_order_id', $source->id)->sortBy('position')->values();

                if ($this->moveFrom($source, $locked, $moving, $position, $actor)) {
                    $deleted[] = $source->id;
                }
            }

            // Step 4: totals of the destination, and the request lines follow the moved ones
            $this->amounts->refreshTotals($locked);
            $this->linker->link($this->requestLineCodes($lines, $locked));

            return ['purchase_order' => $locked, 'deleted_purchase_order_ids' => $deleted];
        });
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Collection<int, PurchaseOrder> keyed by id
     */
    private function lockOrders(PurchaseOrder $destination, array $lineIds): Collection
    {
        $ids = PurchaseOrderLine::query()->whereIn('id', $lineIds)->pluck('purchase_order_id')->push($destination->getKey())->unique();

        return PurchaseOrder::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseOrder>  $orders
     * @return Collection<int, PurchaseOrderLine> keyed by id
     */
    private function lockLines(array $lineIds, Collection $orders): Collection
    {
        $lines = PurchaseOrderLine::query()->whereIn('id', $lineIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // A line moved or deleted by a concurrent request since the orders were locked
        $errors = [];

        foreach ($lineIds as $index => $id) {
            if (! $lines->has($id) || ! $orders->has($lines->get($id)->purchase_order_id)) {
                $errors["line_ids.{$index}"] = [__('The line is no longer available.')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $sources
     */
    private function assertEditable(PurchaseOrder $destination, Collection $sources): void
    {
        $this->orders->assertEditable($destination);
        $sources->each(fn (PurchaseOrder $source) => $this->orders->assertEditable($source));
    }

    /**
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseOrderLine>  $lines
     * @param  Collection<int, PurchaseOrder>  $orders  the locked orders, keyed by id
     */
    private function assertCompatible(PurchaseOrder $destination, array $lineIds, Collection $lines, Collection $orders): void
    {
        $errors = [];

        foreach ($lineIds as $index => $id) {
            $source = $orders->get($lines->get($id)->purchase_order_id);

            if ($source->is($destination)) {
                $errors["line_ids.{$index}"] = [__('The line already belongs to this purchase order.')];
            } elseif ((int) $source->supplier_id !== (int) $destination->supplier_id || (int) $source->company_id !== (int) $destination->company_id) {
                $errors["line_ids.{$index}"] = [__('The line belongs to a purchase order of another supplier or company.')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Move $moving out of $source: snapshot, move, totals, log, and delete the
     * source when nothing is left in it.
     *
     * @param  Collection<int, PurchaseOrderLine>  $moving
     * @return bool whether the source was deleted
     */
    private function moveFrom(PurchaseOrder $source, PurchaseOrder $destination, Collection $moving, int &$position, User $actor): bool
    {
        $snapshot = $this->snapshot($source);
        $movedIds = [];

        foreach ($moving as $line) {
            $line->forceFill(['purchase_order_id' => $destination->id, 'position' => ++$position])->save();
            $movedIds[] = $line->id;
        }

        $emptied = ! $source->lines()->exists();

        if (! $emptied) {
            $this->amounts->refreshTotals($source);
        }

        PurchaseOrderMergeLog::query()->create([
            'destination_purchase_order_id' => $destination->id,
            'source_purchase_order_id' => $emptied ? null : $source->id,
            'source_code' => $source->code(),
            'source_snapshot' => $snapshot,
            'moved_line_ids' => $movedIds,
            'source_action' => $emptied ? PurchaseOrderMergeAction::Deleted : PurchaseOrderMergeAction::Partial,
            'performed_by' => $actor->id,
        ]);

        // delete() fires the model events: audit trail and attachment cleanup
        if ($emptied) {
            $source->delete();
        }

        return $emptied;
    }

    /**
     * @return array{header: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    private function snapshot(PurchaseOrder $source): array
    {
        return [
            'header' => Arr::only($source->attributesToArray(), self::SNAPSHOT_HEADER_KEYS) + ['code' => $source->code()],
            'lines' => $source->lines()->get()->map(fn (PurchaseOrderLine $line): array => Arr::except(
                $line->attributesToArray(),
                ['created_at', 'updated_at'],
            ))->all(),
        ];
    }

    /**
     * @param  Collection<int, PurchaseOrderLine>  $lines
     * @return array<int, string> request line id => destination code
     */
    private function requestLineCodes(Collection $lines, PurchaseOrder $destination): array
    {
        return $lines->pluck('purchase_request_line_id')->filter()
            ->mapWithKeys(fn (mixed $id): array => [(int) $id => $destination->code()])
            ->all();
    }

    /**
     * @return array<int, PurchaseOrderStatus>
     */
    private function editableStatuses(): array
    {
        return array_values(array_filter(PurchaseOrderStatus::cases(), fn (PurchaseOrderStatus $status): bool => $status->isEditable()));
    }
}
