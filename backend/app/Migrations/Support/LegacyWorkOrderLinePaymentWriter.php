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
        $values = [
            'work_order_payment_status_id' => $this->status($record['stato_pagamento'] ?? null, $warnings),
            'payment_agreement' => $this->text($record['accordo_pagamento'] ?? null),
            'has_unpaid' => filter_var($record['insoluti'] ?? null, FILTER_VALIDATE_BOOLEAN),
        ];

        // Step 2: the same values on every line of the commessa.
        foreach ($workOrder->quoteLines()->pluck('quote_lines.id') as $lineId) {
            WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $lineId, ...$values]);
        }
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
