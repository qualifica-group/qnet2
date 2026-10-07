<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use RuntimeException;

/**
 * `work-order-line-payments` migration source (spec 0201, D-5/D-14): copies the
 * legacy per-commessa payment data (`orderisos`: `stato_pagamento`,
 * `accordo_pagamento`, `insoluti`) onto EVERY line of the migrated commessa.
 *
 * Reads the same `work-orders` endpoint as WorkOrdersSource, but is a source of
 * its own because that one skips commesse already migrated (`existsByOldId`).
 * Idempotent: each line's record is upserted, so a re-run updates instead of
 * duplicating. A commessa not migrated yet fails its row; a legacy status key
 * with no `work_order_payment_statuses.old_id` leaves the status empty with a
 * warning. Written directly (no WorkOrderLinePaymentWriter) and without
 * activity log: a migration notifies nobody (D-13).
 */
class WorkOrderLinePaymentsSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'work-order-line-payments';
    }

    public function label(): string
    {
        return 'Work order line payments';
    }

    public function endpoint(): string
    {
        return 'work-orders';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'stato_pagamento', 'label' => 'Payment status (legacy key)', 'type' => 'number'],
            ['id' => 'accordo_pagamento', 'label' => 'Payment agreement', 'type' => 'string'],
            ['id' => 'insoluti', 'label' => 'Unpaid', 'type' => 'boolean'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'stato_pagamento' => $record['stato_pagamento'] ?? null,
            'accordo_pagamento' => $record['accordo_pagamento'] ?? null,
            'insoluti' => $this->legacyBoolean($record['insoluti'] ?? null),
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [];

        // Step 1: the commessa must already be migrated
        $workOrder = WorkOrder::query()->where('old_id', $externalId)->first();

        if ($workOrder === null) {
            throw new RuntimeException("Work order not migrated (legacy id {$externalId}); migrate work-orders first.");
        }

        // Step 2: the legacy values, mapped
        $values = [
            'work_order_payment_status_id' => $this->resolveStatus($record['stato_pagamento'] ?? null, $warnings),
            'payment_agreement' => $this->blankToNull($record['accordo_pagamento'] ?? null),
            'has_unpaid' => $this->legacyBoolean($record['insoluti'] ?? null),
        ];

        // Step 3: upsert on every line of the commessa
        $lineIds = $workOrder->quoteLines()->pluck('quote_lines.id');

        if ($lineIds->isEmpty()) {
            $warnings[] = 'The work order has no lines; no payment data was written.';
        }

        foreach ($lineIds as $lineId) {
            WorkOrderLinePayment::query()->updateOrCreate(
                ['quote_line_id' => $lineId],
                ['work_order_id' => $workOrder->id, ...$values],
            );
        }

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveStatus(mixed $legacyKey, array &$warnings): ?int
    {
        if ($legacyKey === null || $legacyKey === '' || $legacyKey === 0 || $legacyKey === '0') {
            return null;
        }

        $statusId = $this->resolveOldId(WorkOrderPaymentStatus::class, $legacyKey);

        if ($statusId === null) {
            $warnings[] = "Unresolved payment status (legacy key {$legacyKey}); left empty.";
        }

        return $statusId;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function legacyBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
