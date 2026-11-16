<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\WorkOrderPaymentStatus;
use App\Services\ProductCategories\AttributeLayoutPruner;
use Illuminate\Support\Facades\DB;

/**
 * Turns the "Stato Pagamento" flexible field of the commessa into its own
 * column (spec 0402, user directive 2026-11-16): each value (the legacy key,
 * equal to `work_order_payment_statuses.old_id`) is written to
 * `work_orders.work_order_payment_status_id`, then the field leaves every
 * commessa's values, every category and every Commessa layout, and is deleted
 * from the flexible fields with its options (user directive 2026-11-16: no
 * longer a flexible field at all; only work orders ever held it). A
 * commessa still without a status then takes the one of its lines, when every
 * line payment carries the same status (the same legacy datum,
 * `orderisos.stato_pagamento`, imported per line; user decision 2026-11-16).
 * Writes
 * through the query builder: no activity log, no notification. Re-running it
 * changes nothing.
 */
final class WorkOrderPaymentStatusAttributeMover
{
    public const string ATTRIBUTE_CODE = 'stato_pagamento';

    private const int CHUNK_SIZE = 500;

    /**
     * @return array{moved: int, cleared: int, unmapped: int, from_lines: int, detached: int, layouts: int, deleted: int}
     */
    public function handle(): array
    {
        $result = ['moved' => 0, 'cleared' => 0, 'unmapped' => 0, 'from_lines' => 0, 'detached' => 0, 'layouts' => 0, 'deleted' => 0];
        $attribute = Attribute::query()->where('code', self::ATTRIBUTE_CODE)->first();

        // Step 1: the flexible values onto the column, the key out of the flexible values.
        if ($attribute !== null) {
            $this->moveValues($result);
        }

        // Step 2: a commessa still without a status takes the one shared by all its lines.
        $result['from_lines'] = $this->fillFromLines();

        if ($attribute === null) {
            return $result;
        }

        // Step 3: the field out of every category and every Commessa layout.
        $result['detached'] = DB::table('attribute_category')
            ->where('attribute_id', $attribute->id)
            ->where('context', AttributeContext::WorkOrder->value)
            ->delete();
        $result['layouts'] = $this->pruneLayouts();

        // Step 4: the flexible field itself, with its options.
        DB::table('attribute_options')->where('attribute_id', $attribute->id)->delete();
        $result['deleted'] = DB::table('attributes')->where('id', $attribute->id)->delete();

        return $result;
    }

    /**
     * @param  array{moved: int, cleared: int, unmapped: int, from_lines: int, detached: int, layouts: int, deleted: int}  $result
     */
    private function moveValues(array &$result): void
    {
        $statusIds = WorkOrderPaymentStatus::query()->whereNotNull('old_id')->pluck('id', 'old_id')->all();

        DB::table('work_orders')
            ->where('attribute_values', 'like', '%"'.self::ATTRIBUTE_CODE.'"%')
            ->select(['id', 'attribute_values', 'work_order_payment_status_id'])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($statusIds, &$result): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);

                    if (! is_array($values) || ! array_key_exists(self::ATTRIBUTE_CODE, $values)) {
                        continue;
                    }

                    $update = ['attribute_values' => $this->withoutCode($values)];
                    $statusId = $this->statusIdFor($values[self::ATTRIBUTE_CODE], $statusIds, $result);

                    // A status already chosen on the new field wins over the old value.
                    if ($statusId !== null && $row->work_order_payment_status_id === null) {
                        $update['work_order_payment_status_id'] = $statusId;
                        $result['moved']++;
                    }

                    DB::table('work_orders')->where('id', $row->id)->update($update);
                    $result['cleared']++;
                }
            });
    }

    /**
     * Commesse with no status whose line payments all carry one same status.
     */
    private function fillFromLines(): int
    {
        $statuses = DB::table('work_order_line_payments as payments')
            ->join('work_orders', 'work_orders.id', '=', 'payments.work_order_id')
            ->whereNull('work_orders.work_order_payment_status_id')
            ->groupBy('payments.work_order_id')
            ->havingRaw('COUNT(DISTINCT payments.work_order_payment_status_id) = 1')
            ->havingRaw('SUM(CASE WHEN payments.work_order_payment_status_id IS NULL THEN 1 ELSE 0 END) = 0')
            ->selectRaw('payments.work_order_id, MIN(payments.work_order_payment_status_id) as status_id')
            ->pluck('status_id', 'work_order_id');
        $filled = 0;

        foreach ($statuses->groupBy(static fn (int|string $statusId): int => (int) $statusId, preserveKeys: true) as $statusId => $group) {
            foreach (array_chunk($group->keys()->all(), self::CHUNK_SIZE) as $workOrderIds) {
                $filled += DB::table('work_orders')
                    ->whereIn('id', $workOrderIds)
                    ->whereNull('work_order_payment_status_id')
                    ->update(['work_order_payment_status_id' => $statusId]);
            }
        }

        return $filled;
    }

    /**
     * @param  array<int|string, int>  $statusIds  status id by legacy key
     * @param  array{moved: int, cleared: int, unmapped: int, from_lines: int, detached: int, layouts: int, deleted: int}  $result
     */
    private function statusIdFor(mixed $value, array $statusIds, array &$result): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $statusId = $statusIds[(string) $value] ?? null;

        if ($statusId === null) {
            $result['unmapped']++;
        }

        return $statusId;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function withoutCode(array $values): ?string
    {
        unset($values[self::ATTRIBUTE_CODE]);

        return $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE);
    }

    private function pruneLayouts(): int
    {
        $pruned = 0;
        $layouts = DB::table('attribute_layouts')
            ->where('context', AttributeContext::WorkOrder->value)
            ->where('layout', 'like', '%"'.self::ATTRIBUTE_CODE.'"%')
            ->get(['id', 'layout']);

        foreach ($layouts as $row) {
            $layout = json_decode((string) $row->layout, true);
            $kept = AttributeLayoutPruner::prune(is_array($layout) ? $layout : [], static fn (?string $code): bool => $code !== self::ATTRIBUTE_CODE);

            DB::table('attribute_layouts')->where('id', $row->id)->update(['layout' => json_encode($kept, JSON_UNESCAPED_UNICODE)]);
            $pruned++;
        }

        return $pruned;
    }
}
