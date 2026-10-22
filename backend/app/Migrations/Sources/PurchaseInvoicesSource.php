<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\PurchaseInvoiceType;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyCostTypeLookup;
use App\Migrations\Support\LegacyPurchaseInvoiceWriter;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderInvoiceReceiptService;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * `purchase-invoices` migration source (spec 0298): the legacy supplier
 * invoices, one document per merged group of FTP (D-1), billing the purchase
 * orders already migrated from the same legacy records (spec 0296). Written
 * directly to the models (PurchaseInvoiceService would plan a new schedule,
 * refuse a draft order and needs a validated payload) inside the engine's
 * per-row transaction, without activity log; nothing is notified.
 *
 * Here: `old_id` lookups, the supplier number unique per year, the qnet-2
 * amounts and the positive total (D-4), the legacy schedule and payment
 * (LegacyPurchaseInvoiceWriter) and the receipt of what the invoice bills,
 * also on a draft order (D-2).
 */
class PurchaseInvoicesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const int NUMBER_MAX = 30;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyPurchaseInvoiceWriter $writer,
        private readonly LegacyCostTypeLookup $costTypes,
        private readonly PurchaseOrderInvoiceReceiptService $invoiceReceipts,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'purchase-invoices';
    }

    public function label(): string
    {
        return 'Purchase invoices';
    }

    public function endpoint(): string
    {
        return 'purchase-invoices';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('users'),
            MigrationDependency::required('companies'),
            MigrationDependency::required('company-sites'),
            MigrationDependency::required('registries'),
            MigrationDependency::required('payment-methods'),
            MigrationDependency::required('purchase-orders'),
            MigrationDependency::optional('operational-sites'),
            MigrationDependency::optional('vat-rates'),
            MigrationDependency::optional('work-orders'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'document_number', 'label' => 'Number', 'type' => 'string'],
            ['id' => 'document_date', 'label' => 'Date', 'type' => 'date'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id)', 'type' => 'number'],
            ['id' => 'purchase_order_ids', 'label' => 'Purchase orders (external ids)', 'type' => 'string'],
            ['id' => 'is_paid', 'label' => 'Paid', 'type' => 'boolean'],
            ['id' => 'lines', 'label' => 'Lines', 'type' => 'number'],
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

        if ($this->existsByOldId(PurchaseInvoice::class, $externalId)) {
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

        // Step 1: the required anchors, the invoiced orders and the supplier number (D-6).
        $supplierId = $this->requiredOldId(Registry::class, $record['supplier_id'] ?? null, 'Supplier');
        $documentDate = CarbonImmutable::parse($this->requiredText($record['document_date'] ?? null, 'document_date'));
        $number = $this->number($record['document_number'] ?? null, $supplierId, $documentDate->year);
        $orderIds = $this->orderIds($record['purchase_order_ids'] ?? []);
        $creator = User::query()->findOrFail($this->requiredOldId(User::class, $record['creator_user_id'] ?? null, 'Creator'));

        // Step 2: lines and qnet-2 amounts; a document must have a positive total (D-4).
        $computed = $this->writer->computeLines((array) ($record['lines'] ?? []), $orderIds, $warnings);

        if (bccomp($computed['total'], '0', 2) <= 0) {
            throw new RuntimeException("Total {$computed['total']} is not positive; the invoice is not imported.");
        }

        // Step 3: the header (document_year derived on save), lines, orders and schedule.
        $invoice = new PurchaseInvoice([
            ...$this->companyAndSite($record),
            'document_type' => PurchaseInvoiceType::Invoice,
            'supplier_id' => $supplierId,
            'document_number' => $number,
            'document_date' => $documentDate->toDateString(),
            'payment_method_id' => $this->requiredOldId(PaymentMethod::class, $record['payment_method_id'] ?? null, 'Payment method'),
            'cost_type_id' => $this->costTypes->idFor($record['cost_type_name'] ?? null, 'Header'),
            'operational_site_id' => $this->remapLegacyId(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings),
            'work_order_id' => $this->sharedWorkOrderId($computed['lines']),
            'net_amount' => $computed['net'],
            'vat_amount' => $computed['vat'],
            'total_amount' => $computed['total'],
            'created_by' => $creator->id,
        ]);
        $invoice->save();
        $invoice->lines()->createMany($computed['lines']);
        $invoice->purchaseOrders()->sync($orderIds);
        $this->writer->writeSchedule($invoice, (array) ($record['installments'] ?? []), ($record['is_paid'] ?? false) ? $this->paidAt($record) : null, $warnings);

        // Step 4: the invoice receives what it bills, also on a draft order (D-2), then the legacy anchors.
        $this->invoiceReceipts->receive($invoice, $creator, false, requireReceivable: false);
        $this->finalizeLegacyRecord($invoice, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $invoice);
    }

    /**
     * @throws RuntimeException when the number is missing, too long or already used by the supplier in the year
     */
    private function number(mixed $value, int $supplierId, int $year): string
    {
        $number = $this->requiredText($value, 'document_number');

        if (mb_strlen($number) > self::NUMBER_MAX) {
            throw new RuntimeException("Number '{$number}' longer than ".self::NUMBER_MAX.' characters; the invoice is not imported.');
        }

        $taken = PurchaseInvoice::query()->where('supplier_id', $supplierId)->where('document_number', $number)->where('document_year', $year)->exists();

        if ($taken) {
            throw new RuntimeException("Number '{$number}' already used by the supplier in {$year}; the invoice is not imported.");
        }

        return $number;
    }

    /**
     * @return array<int, int>
     *
     * @throws RuntimeException when an invoiced order is not migrated
     */
    private function orderIds(mixed $legacyIds): array
    {
        $ids = [];

        foreach ((array) $legacyIds as $legacyId) {
            $ids[] = $this->requiredOldId(PurchaseOrder::class, $legacyId, 'Purchase order');
        }

        return $ids === [] ? throw new RuntimeException('No purchase order; the invoice is not imported.') : $ids;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function paidAt(array $record): string
    {
        return ($this->legacyTimestamp($record['paid_at'] ?? null) ?? $this->legacyTimestamp($record['updated_at'] ?? null) ?? CarbonImmutable::now())->toDateString();
    }

    /**
     * The work order of every line when they all share one, the header default of new lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function sharedWorkOrderId(array $lines): ?int
    {
        $ids = array_values(array_unique(array_column($lines, 'work_order_id')));

        return count($ids) === 1 && $ids[0] !== null ? (int) $ids[0] : null;
    }
}
