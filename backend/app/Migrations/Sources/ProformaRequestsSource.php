<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use RuntimeException;

/**
 * `proforma-requests` migration source (spec 0300): the legacy requests to
 * issue a proforma, written directly to the model (ProformaRequestService
 * derives the requests from the work order lines and refuses duplicates),
 * without activity log; nothing is notified. The legacy sends kind, status
 * and note ready (D-1); here the `old_id` lookups and the payment method
 * snapshot of the quote (spec 0193 D-9).
 */
class ProformaRequestsSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    public function key(): string
    {
        return 'proforma-requests';
    }

    public function label(): string
    {
        return 'Proforma requests';
    }

    public function endpoint(): string
    {
        return 'proforma-requests';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::required('quotes'),
            MigrationDependency::required('work-orders'),
            MigrationDependency::optional('registries'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'work_order_id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'kind', 'label' => 'Kind', 'type' => 'string'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id)', 'type' => 'number'],
            ['id' => 'status', 'label' => 'Status', 'type' => 'string'],
            ['id' => 'note', 'label' => 'Note', 'type' => 'string'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(ProformaRequest::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));
        $kind = ProformaRequestKind::tryFrom((string) ($record['kind'] ?? ''))
            ?? throw new RuntimeException("Kind '".($record['kind'] ?? '')."' is not valid; the record is not imported.");
        $status = ProformaRequestStatus::tryFrom((string) ($record['status'] ?? ''))
            ?? throw new RuntimeException("Status '".($record['status'] ?? '')."' is not valid; the record is not imported.");

        // Step 1: the required anchors; the payment method is the quote's snapshot (spec 0193 D-9).
        $quote = Quote::query()->findOrFail($this->requiredOldId(Quote::class, $record['quote_id'] ?? null, 'Quote'));

        // Step 2: the request, quietly, with the legacy people and state.
        $proformaRequest = new ProformaRequest([
            'work_order_id' => $this->requiredOldId(WorkOrder::class, $record['work_order_id'] ?? null, 'Work order'),
            'quote_id' => $quote->id,
            'kind' => $kind,
            'supplier_id' => $this->remapLegacyId(Registry::class, $record['supplier_id'] ?? null, 'supplier_id', $warnings),
            'payment_method_id' => $quote->payment_method_id,
            'status' => $status,
            'issued_at' => $status === ProformaRequestStatus::Issued ? $this->legacyTimestamp($record['issued_at'] ?? null) : null,
            'note' => $this->requiredText($record['note'] ?? null, 'note'),
            'assigned_to' => $this->requiredOldId(User::class, $record['assigned_to_user_id'] ?? null, 'Assignee'),
            'assigned_by' => $this->requiredOldId(User::class, $record['assigned_by_user_id'] ?? null, 'Requester'),
        ]);
        $proformaRequest->saveQuietly();

        // Step 3: the legacy anchors.
        $this->finalizeLegacyRecord($proformaRequest, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $proformaRequest);
    }
}
