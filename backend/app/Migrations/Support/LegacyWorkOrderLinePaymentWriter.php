<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;

/**
 * Writes the legacy payment data of a freshly imported commessa on every one
 * of its lines (spec 0201, D-5/D-14; spec 0189 G-24): the legacy keeps one
 * `stato_pagamento` / `accordo_pagamento` / `insoluti` per commessa, qnet-2
 * one per line. A legacy status key with no `work_order_payment_statuses.old_id`
 * leaves the status empty with a warning. The caller runs without activity
 * log: a migration notifies nobody (D-13).
 *
 * The same legacy status is also the commessa's own "Stato pagamento" (spec
 * 0402): written on import, and on realign only while the commessa has none
 * (a status chosen in qnet-2 is never overwritten).
 */
final class LegacyWorkOrderLinePaymentWriter
{
    /**
     * @param  array<string, mixed>  $record  the legacy commessa
     * @param  array<int, string>  $warnings
     */
    public function write(WorkOrder $workOrder, array $record, array &$warnings): void
    {
        // Step 1: the legacy values, mapped once for the whole commessa.
        $values = $this->values($record, $warnings);

        // Step 2: the same values on every line of the commessa.
        foreach ($workOrder->quoteLines()->pluck('quote_lines.id') as $lineId) {
            WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $lineId, ...$values]);
        }

        // Step 3: the status of the commessa itself.
        $this->writeWorkOrderStatus($workOrder, $values['work_order_payment_status_id']);
    }

    /**
     * Realigns the payment data of an already imported commessa (spec 0372, D-11):
     * one row per current line, found or created on (`work_order_id`, `quote_line_id`)
     * so a re-run never duplicates it, written quietly and only when a value differs.
     *
     * @param  array<string, mixed>  $record  the legacy commessa
     * @param  array<int, string>  $warnings
     * @return bool true only when a row was created or changed
     */
    public function sync(WorkOrder $workOrder, array $record, array &$warnings): bool
    {
        // Step 1: the legacy values, mapped once for the whole commessa.
        $values = $this->values($record, $warnings);
        $written = false;

        // Step 2: create or realign the row of every current line.
        foreach ($workOrder->quoteLines()->pluck('quote_lines.id') as $lineId) {
            $payment = WorkOrderLinePayment::query()->firstOrNew(['work_order_id' => $workOrder->id, 'quote_line_id' => $lineId]);
            $payment->fill($values);

            if ($payment->isDirty()) {
                $payment->saveQuietly();
                $written = true;
            }
        }

        // Step 3: the status of the commessa itself, only where it has none.
        if ($workOrder->work_order_payment_status_id === null && $this->writeWorkOrderStatus($workOrder, $values['work_order_payment_status_id'])) {
            $written = true;
        }

        return $written;
    }

    /** @return bool true when the status changed */
    private function writeWorkOrderStatus(WorkOrder $workOrder, ?int $statusId): bool
    {
        if ($statusId === null || $workOrder->work_order_payment_status_id === $statusId) {
            return false;
        }

        $workOrder->forceFill(['work_order_payment_status_id' => $statusId])->saveQuietly();

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{work_order_payment_status_id: ?int, payment_agreement: ?string, has_unpaid: bool}
     */
    private function values(array $record, array &$warnings): array
    {
        return [
            'work_order_payment_status_id' => $this->status($record['stato_pagamento'] ?? null, $warnings),
            'payment_agreement' => $this->text($record['accordo_pagamento'] ?? null),
            'has_unpaid' => filter_var($record['insoluti'] ?? null, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function status(mixed $legacyKey, array &$warnings): ?int
    {
        if ($legacyKey === null || $legacyKey === '') {
            return null;
        }

        $statusId = WorkOrderPaymentStatus::query()->where('old_id', $legacyKey)->value('id');

        if ($statusId === null) {
            $warnings[] = "Unresolved payment status (legacy key {$legacyKey}); left empty.";
        }

        return $statusId === null ? null : (int) $statusId;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
