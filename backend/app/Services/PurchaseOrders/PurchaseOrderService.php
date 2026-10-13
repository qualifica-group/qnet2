<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business logic of the purchase orders (spec 0210): create (with the monthly
 * copies of D-16), full edit with line sync, delete. Amounts are always
 * computed here (D-12); header and lines are writable, and the order
 * deletable, only while it is a draft or in validation (D-10). Deleting a line
 * linked to a purchase request line releases it (D-18).
 */
class PurchaseOrderService
{
    /** Relations PurchaseOrderResource reads (lazy loading is forbidden). */
    public const array RESOURCE_RELATIONS = [
        'supplier:id,name,is_qualified_supplier',
        'supplier.personalData.contacts',
        'company:id,denomination',
        'companySite:id,name',
        'operationalSite.addresses.city',
        'paymentMethod:id,name',
        'customer:id,name',
        'workOrder:id,code,title',
        'quote:id,title',
        'opportunity:id,name',
        'sectors:id,name',
        'createdBy:id,name',
        'lines.product:id,name',
        'lines.costType:id,name',
        'lines.unitOfMeasure:id,name,symbol',
        'lines.vatRate:id,name,rate',
        'lines.workOrder:id,code,title',
        'lines.purchaseRequestLine:id,purchase_request_id',
    ];

    /** Computed columns of a line. */
    private const array AMOUNT_KEYS = ['taxable_amount', 'vat_amount', 'total_amount'];

    public function __construct(
        private readonly PurchaseOrderAmountCalculator $amounts,
        private readonly PurchaseOrderNumberAllocator $numbers,
        private readonly PurchaseRequestLineLinker $linker,
        private readonly PurchaseOrderDeletionGuard $deletion,
    ) {}

    /**
     * Create the order, or the N independent monthly copies (D-16), in one
     * transaction. The first one is the order the client works on.
     *
     * @return array<int, PurchaseOrder> the created orders, the first one first
     */
    public function create(PurchaseOrderData $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            // Step 1: one group id ties the copies together
            $groupId = $data->copies > 1 ? (string) Str::uuid() : null;
            $baseDate = CarbonImmutable::parse($data->header['order_date']);
            $created = [];

            // Step 2: copy i is dated i months after the first, month end preserved
            for ($copy = 0; $copy < $data->copies; $copy++) {
                $header = [...$data->header, 'order_date' => $baseDate->addMonthsNoOverflow($copy)->toDateString()];
                $created[] = $this->createOne($header, $data->lines, $data->sectorIds, $actor, $groupId);
            }

            return $created;
        });
    }

    /**
     * Create a single draft order with its number, lines, sectors and totals.
     * Each line may carry `purchase_request_line_id` (set only by the
     * generation from RDA lines, D-17).
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, int>|null  $sectorIds
     */
    public function createOne(array $header, array $lines, ?array $sectorIds, User $actor, ?string $copyGroupId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $lines, $sectorIds, $actor, $copyGroupId): PurchaseOrder {
            // Step 1: header, numbered per (company, year of the order date); created_by is never client input
            $year = CarbonImmutable::parse($header['order_date'])->year;
            $order = new PurchaseOrder($header);
            $order->forceFill([
                'year' => $year,
                'number' => $this->numbers->next((int) $header['company_id'], $year),
                'status' => PurchaseOrderStatus::Draft,
                'copy_group_id' => $copyGroupId,
                'created_by' => $actor->id,
            ])->save();

            // Step 2: lines, sectors, then the totals
            $rates = $this->amounts->vatRates($lines);

            foreach ($lines as $index => $line) {
                $this->createLine($order, $line, $index + 1, $rates);
            }

            if ($sectorIds !== null) {
                $order->sectors()->sync($sectorIds);
            }

            $this->amounts->refreshTotals($order);

            return $order;
        });
    }

    public function update(PurchaseOrder $order, PurchaseOrderData $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data, $actor): PurchaseOrder {
            // Step 1: serialize on the order and refuse a locked one
            $locked = $this->lock($order);
            $this->assertEditable($locked);
            $this->assertCompanyUnchanged($locked, $data);

            // Step 2: check the line payload against the stored lines before writing anything
            $existing = $locked->lines()->lockForUpdate()->get()->keyBy('id');
            $this->assertLinesBelong($existing, $data->lines);
            $removed = $this->removedLines($existing, $data->lines);

            // Step 3: release the request lines of the removed lines (D-18), then delete them
            $this->linker->unlink($removed, $actor);
            $removed->each(fn (PurchaseOrderLine $line) => $line->delete());

            // Step 4: header and sectors
            $locked->fill($data->header)->save();

            if ($data->sectorIds !== null) {
                $locked->sectors()->sync($data->sectorIds);
            }

            // Step 5: update / create the lines, then the totals
            $rates = $this->amounts->vatRates($data->lines);

            foreach ($data->lines as $index => $line) {
                $stored = $line['id'] === null ? null : $existing->get($line['id']);
                $stored === null
                    ? $this->createLine($locked, $line, $index + 1, $rates)
                    : $this->updateLine($stored, $line, $index + 1, $rates);
            }

            $this->amounts->refreshTotals($locked);

            return $locked;
        });
    }

    /** Delete the order, its lines (releasing the linked request lines, D-18) and attachments. */
    public function delete(PurchaseOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $locked = $this->lock($order);
            $this->deletion->assertDeletable($locked);

            $lines = $locked->lines()->lockForUpdate()->get();
            $this->linker->unlink($lines, $actor);

            // One by one: the DB cascade would skip the model events (audit log)
            $lines->each(fn (PurchaseOrderLine $line) => $line->delete());
            $locked->delete();
        });
    }

    public function detail(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->with(self::RESOURCE_RELATIONS)->withCount('mergeLogs')->findOrFail($order->getKey());
    }

    public function assertEditable(PurchaseOrder $order): void
    {
        if (! $order->status->isEditable()) {
            abort(409, __('purchase_orders.not_editable'));
        }
    }

    /** The numbering is per company: moving an order to another one would break it (spec 0244, D-1). */
    private function assertCompanyUnchanged(PurchaseOrder $locked, PurchaseOrderData $data): void
    {
        if ((int) $data->header['company_id'] !== (int) $locked->company_id) {
            throw ValidationException::withMessages(['company_id' => [__('purchase_orders.company_locked')]]);
        }
    }

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $rates  vat_rate_id => rate
     */
    private function createLine(PurchaseOrder $order, array $line, int $position, array $rates): void
    {
        $amounts = $this->amounts->line($line, $rates);

        $model = new PurchaseOrderLine($this->lineContent($line, $position, $amounts));
        $model->forceFill(Arr::only($amounts, self::AMOUNT_KEYS) + [
            'purchase_order_id' => $order->id,
            'purchase_request_line_id' => $line['purchase_request_line_id'] ?? null,
        ]);
        $model->save();
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $rates
     */
    private function updateLine(PurchaseOrderLine $stored, array $line, int $position, array $rates): void
    {
        $amounts = $this->amounts->line($line, $rates);

        $stored->fill($this->lineContent($line, $position, $amounts))->forceFill(Arr::only($amounts, self::AMOUNT_KEYS))->save();
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, string>  $amounts  normalized quantity and unit price among them
     * @return array<string, mixed>
     */
    private function lineContent(array $line, int $position, array $amounts): array
    {
        return [
            'position' => $position,
            'product_id' => $line['product_id'],
            'description' => $line['description'],
            'cost_type_id' => $line['cost_type_id'],
            'unit_of_measure_id' => $line['unit_of_measure_id'],
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $line['vat_rate_id'],
            'work_order_id' => $line['work_order_id'],
        ];
    }

    /**
     * Existing lines must belong to this order and appear once.
     *
     * @param  Collection<int, PurchaseOrderLine>  $existing  keyed by id
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLinesBelong(Collection $existing, array $lines): void
    {
        $errors = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            if ($line['id'] === null) {
                continue;
            }

            if ($existing->get($line['id']) === null || in_array($line['id'], $seen, true)) {
                $errors["lines.{$index}.id"] = [__('The line does not belong to this purchase order.')];

                continue;
            }

            $seen[] = $line['id'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrderLine>  $existing
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, PurchaseOrderLine>
     */
    private function removedLines(Collection $existing, array $lines): Collection
    {
        $kept = array_filter(array_column($lines, 'id'));

        return $existing->reject(fn (PurchaseOrderLine $line): bool => in_array($line->id, $kept, true))->values();
    }
}
