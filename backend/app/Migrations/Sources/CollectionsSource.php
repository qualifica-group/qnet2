<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\ResidualMode;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Services\Invoices\InvoiceCollectionService;
use App\Support\Amounts\LineAmountCalculator;
use RuntimeException;

/**
 * `collections` migration source (spec 0302): every legacy collection, none
 * filtered. A collection already reflected on the installments (the active
 * invoice source brings the collected schedules) is skipped; the missing part
 * is recorded on the open installments through InvoiceCollectionService
 * (which also accrues the commissions); a collection with no document, or on
 * a document deleted or not imported, fails with the reason. Creates no
 * record of its own, so a re-run finds everything collected and skips it.
 */
class CollectionsSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;

    private const string INVOICE_SOURCE = 'proformas';

    public function __construct(
        ExternalApiClient $client,
        private readonly InvoiceCollectionService $collections,
        private readonly LineAmountCalculator $amounts,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'collections';
    }

    public function label(): string
    {
        return 'Collections';
    }

    public function endpoint(): string
    {
        return 'collections';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [MigrationDependency::required('invoices')];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'kind', 'label' => 'Kind', 'type' => 'string'],
            ['id' => 'collected_at', 'label' => 'Collected at', 'type' => 'date'],
            ['id' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'customer_id', 'label' => 'Customer (external id)', 'type' => 'number'],
            ['id' => 'movements', 'label' => 'Movements', 'type' => 'number'],
        ];
    }

    /**
     * The registration id; an empty header is prefixed so the report tells the two apart.
     */
    protected function externalId(array $record): int|string|null
    {
        $id = $record['id'] ?? null;

        return $id === null || ($record['kind'] ?? null) !== 'empty_header' ? $id : "header:{$id}";
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
        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));
        $movements = (array) ($record['movements'] ?? []);

        // Step 1: a collection must reach a document (D-2).
        if ($movements === []) {
            $description = trim((string) ($record['description'] ?? '')) ?: '-';

            throw new RuntimeException("No document linked in the legacy (description: {$description}); the collection cannot be assigned.");
        }

        $collectedAt = trim((string) ($record['collected_at'] ?? '')) ?: throw new RuntimeException('collected_at is required.');
        $recorded = '0.00';

        // Step 2: record on each document only what qnet-2 is still missing.
        foreach ($movements as $movement) {
            $recorded = bcadd($recorded, $this->recordMovement((array) $movement, $collectedAt, $warnings), 2);
        }

        if (bccomp($recorded, '0', 2) === 0) {
            return MigrationRowOutcome::skipped($warnings);
        }

        $warnings[] = "Collected {$recorded} on {$collectedAt}.";

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * @param  array<string, mixed>  $movement
     * @param  array<int, string>  $warnings
     * @return string the amount recorded
     *
     * @throws RuntimeException when the document is deleted in the legacy or not imported
     */
    private function recordMovement(array $movement, string $collectedAt, array &$warnings): string
    {
        $legacyId = $movement['proforma_id'] ?? null;

        if ($legacyId === null) {
            throw new RuntimeException('Document deleted in the legacy; the collection cannot be assigned.');
        }

        $invoice = Invoice::query()->where('old_source', self::INVOICE_SOURCE)->where('old_id', $legacyId)->first()
            ?? throw new RuntimeException("Document (legacy id {$legacyId}) not imported; the collection cannot be assigned.");

        // Step 1: what is missing = this movement, within the legacy total and the document residual.
        $collected = $this->amounts->normalize($invoice->installments()->sum('collected_amount'));
        $stillLegacy = bcsub($this->amounts->roundToCents($movement['document_collected_total'] ?? 0), $collected, 2);
        $missing = $this->minimum($this->amounts->roundToCents($movement['amount'] ?? 0), $stillLegacy);

        if (bccomp($missing, '0', 2) <= 0) {
            return '0.00';
        }

        $residual = bcsub((string) $invoice->total_amount, $collected, 2);

        if (bccomp($missing, $residual, 2) > 0) {
            $warnings[] = "Document (legacy id {$legacyId}): {$missing} exceeds the residual {$residual}, only the residual is recorded.";
            $missing = $residual;
        }

        // Step 2: fill the open installments in order.
        return $this->fillInstallments($invoice, $missing, $collectedAt);
    }

    private function fillInstallments(Invoice $invoice, string $missing, string $collectedAt): string
    {
        $recorded = '0.00';

        while (bccomp($missing, '0', 2) > 0) {
            $installment = $invoice->installments()->whereNull('collected_amount')->orderBy('sequence')->first();

            if (! $installment instanceof InvoiceInstallment) {
                break;
            }

            $take = $this->minimum($missing, (string) $installment->amount);
            $this->collections->record($installment, [
                'collected_amount' => $take,
                'collected_at' => $collectedAt,
                'residual_mode' => ResidualMode::NewInstallment->value,
                'residual_due_date' => $installment->due_date->toDateString(),
            ]);
            $missing = bcsub($missing, $take, 2);
            $recorded = bcadd($recorded, $take, 2);
        }

        return $recorded;
    }

    private function minimum(string $first, string $second): string
    {
        return bccomp($first, $second, 2) <= 0 ? $first : $second;
    }
}
