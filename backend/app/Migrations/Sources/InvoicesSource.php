<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\InvoiceType;
use App\Enums\ProformaRequestStatus;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyDocumentNumberer;
use App\Migrations\Support\LegacyInvoiceWriter;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\PaymentMethod;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * `invoices` migration source (spec 0301): the legacy customer proformas and
 * institution proformas as active documents, with lines, schedule and
 * collections, linked to the proforma request they were issued from. Written
 * directly to the models (InvoiceService allocates the number, needs a pending
 * request and plans a new schedule) inside the engine's per-row transaction,
 * without activity log; nothing is notified.
 *
 * The anchor is (`old_source`, `old_id`): the two legacy tables share ids
 * (D-7). Here: `old_id` lookups, the number unique per company and year
 * (LegacyDocumentNumberer, D-8), the qnet-2 amounts and the positive total
 * (D-2), the request link and its issued state.
 */
class InvoicesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const array OLD_SOURCES = ['proformas', 'proforma_ente'];

    private const int EXTERNAL_NUMBER_MAX = 30;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyInvoiceWriter $writer,
        private readonly LegacyDocumentNumberer $numberer,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'invoices';
    }

    public function label(): string
    {
        return 'Invoices';
    }

    public function endpoint(): string
    {
        return 'invoices';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::required('companies'),
            MigrationDependency::required('registries'),
            MigrationDependency::required('payment-methods'),
            MigrationDependency::required('vat-rates'),
            MigrationDependency::optional('work-orders'),
            MigrationDependency::optional('quotes'),
            MigrationDependency::optional('products'),
            MigrationDependency::optional('financial-accounts'),
            MigrationDependency::optional('proforma-requests'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'source', 'label' => 'Legacy table', 'type' => 'string'],
            ['id' => 'type', 'label' => 'Type', 'type' => 'string'],
            ['id' => 'number', 'label' => 'Number', 'type' => 'number'],
            ['id' => 'document_date', 'label' => 'Date', 'type' => 'date'],
            ['id' => 'customer_id', 'label' => 'Customer (external id)', 'type' => 'number'],
            ['id' => 'lines', 'label' => 'Lines', 'type' => 'number'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
        ];
    }

    /**
     * The legacy id, prefixed by its table for the institution proformas so the report tells the two apart.
     */
    protected function externalId(array $record): int|string|null
    {
        $id = $record['id'] ?? null;

        return $id === null || ($record['source'] ?? null) === self::OLD_SOURCES[0] ? $id : "{$record['source']}:{$id}";
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
        $source = (string) ($record['source'] ?? '');
        $legacyId = $record['id'] ?? null;

        if ($legacyId === null || ! in_array($source, self::OLD_SOURCES, true)) {
            throw new RuntimeException('External id and a known source are required.');
        }

        if (Invoice::query()->where('old_source', $source)->where('old_id', $legacyId)->exists()) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($source, (int) $legacyId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(string $source, int $legacyId, array $record): MigrationRowOutcome
    {
        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));
        $type = InvoiceType::tryFrom((string) ($record['type'] ?? ''))
            ?? throw new RuntimeException("Type '".($record['type'] ?? '')."' is not valid; the document is not imported.");

        // Step 1: the required anchors (D-6).
        $companyId = $this->requiredOldId(Company::class, $record['company_id'] ?? null, 'Company');
        $documentDate = CarbonImmutable::parse($this->requiredText($record['document_date'] ?? null, 'document_date'));

        // Step 2: lines and qnet-2 amounts; a document must have a positive total (D-2).
        $computed = $this->writer->computeLines((array) ($record['lines'] ?? []), $warnings);

        if (bccomp($computed['total'], '0', 2) <= 0) {
            throw new RuntimeException("Total {$computed['total']} is not positive; the document is not imported.");
        }

        // Step 3: the header with a number unique per company and year (D-8).
        $invoice = new Invoice([
            'type' => $type,
            'company_id' => $companyId,
            'year' => $documentDate->year,
            'document_date' => $documentDate->toDateString(),
            'customer_registry_id' => $this->requiredOldId(Registry::class, $record['customer_id'] ?? null, 'Customer'),
            'payment_method_id' => $this->requiredOldId(PaymentMethod::class, $record['payment_method_id'] ?? null, 'Payment method'),
            'work_order_id' => $this->remapLegacyId(WorkOrder::class, $record['work_order_id'] ?? null, 'work_order_id', $warnings),
            'quote_id' => $this->remapLegacyId(Quote::class, $record['quote_id'] ?? null, 'quote_id', $warnings),
            'financial_account_id' => $this->remapLegacyId(FinancialAccount::class, $record['financial_account_id'] ?? null, 'financial_account_id', $warnings),
            'proforma_request_id' => $this->proformaRequestId($record['proforma_request_id'] ?? null, $warnings),
            'net_amount' => $computed['net'],
            'vat_amount' => $computed['vat'],
            'total_amount' => $computed['total'],
            'external_number' => $type === InvoiceType::Invoice ? $this->externalNumber($record['external_number'] ?? null) : null,
            'external_date' => $type === InvoiceType::Invoice ? $this->externalDate($record['external_date'] ?? null, $documentDate, $warnings) : null,
            'internal_note' => $this->blankToNull($record['internal_note'] ?? null),
            'deviation' => $record['deviation'] ?? null,
            'created_by' => $this->requiredOldId(User::class, $record['creator_user_id'] ?? null, 'Creator'),
        ]);
        $invoice->forceFill($this->number($companyId, $documentDate->year, $record, $warnings))->saveQuietly();

        // Step 4: lines, schedule with collections, the request now issued, then the legacy anchors.
        $invoice->lines()->createMany($computed['lines']);
        $this->writer->writeSchedule($invoice, (array) ($record['installments'] ?? []), $warnings);
        $this->issueRequest($invoice);
        $this->finalizeLegacyRecord($invoice, $legacyId, $record, ['old_source' => $source]);

        return MigrationRowOutcome::created($warnings, $invoice);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{number: int, notes: string|null}
     */
    private function number(int $companyId, int $year, array $record, array &$warnings): array
    {
        $legacyNumber = isset($record['number']) ? (int) $record['number'] : null;
        $assigned = $this->numberer->number(Invoice::class, InvoiceNumberSequence::class, $companyId, $year, $legacyNumber, (int) ($record['year_max_number'] ?? 0));
        $notes = $this->blankToNull($record['notes'] ?? null);

        if ($assigned['reassigned'] && $legacyNumber !== null) {
            $reference = "Numero legacy {$legacyNumber}";
            $notes = $notes === null ? $reference : "{$notes}\n\n{$reference}";
            $warnings[] = "Number {$legacyNumber}/{$year} already used: the document takes {$assigned['number']}/{$year}.";
        }

        return ['number' => $assigned['number'], 'notes' => $notes];
    }

    /**
     * The migrated request, unless another document already holds it (one document per request).
     *
     * @param  array<int, string>  $warnings
     */
    private function proformaRequestId(mixed $legacyId, array &$warnings): ?int
    {
        $id = $this->remapLegacyId(ProformaRequest::class, $legacyId, 'proforma_request_id', $warnings);

        if ($id !== null && Invoice::query()->where('proforma_request_id', $id)->exists()) {
            $warnings[] = "Proforma request (legacy id {$legacyId}) already linked to another document, left empty.";

            return null;
        }

        return $id;
    }

    /**
     * A request with a document is issued (spec 0194 D-7); a pending one takes the document date.
     */
    private function issueRequest(Invoice $invoice): void
    {
        if ($invoice->proforma_request_id === null) {
            return;
        }

        ProformaRequest::query()->whereKey($invoice->proforma_request_id)->where('status', ProformaRequestStatus::Pending->value)
            ->update(['status' => ProformaRequestStatus::Issued->value, 'issued_at' => $invoice->document_date]);
    }

    /**
     * A registered document has its external date (spec 0194 D-3); without one it takes the document date.
     *
     * @param  array<int, string>  $warnings
     */
    private function externalDate(mixed $value, CarbonImmutable $documentDate, array &$warnings): string
    {
        $date = $this->blankToNull($value);

        if ($date === null) {
            $warnings[] = 'External number without date: the document date is used.';
        }

        return $date ?? $documentDate->toDateString();
    }

    /**
     * @throws RuntimeException when the external number exceeds the column
     */
    private function externalNumber(mixed $value): ?string
    {
        $number = $this->blankToNull($value);

        if ($number !== null && mb_strlen($number) > self::EXTERNAL_NUMBER_MAX) {
            throw new RuntimeException("External number '{$number}' longer than ".self::EXTERNAL_NUMBER_MAX.' characters; the document is not imported.');
        }

        return $number;
    }
}
