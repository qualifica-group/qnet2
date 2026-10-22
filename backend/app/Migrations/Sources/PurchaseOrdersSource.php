<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\PurchaseOrderStatus;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyDocumentNumberer;
use App\Migrations\Support\LegacyPurchaseOrderLineWriter;
use App\Models\OperationalSite;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNumberSequence;
use App\Models\Registry;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * `purchase-orders` migration source (spec 0296): the legacy ODA, open ones
 * and the ones turned into a supplier invoice (D-1), with their lines, the
 * link to the migrated RDA lines and the receipt of a fulfilled order,
 * written DIRECTLY to the models, not through PurchaseOrderService::create()
 * (it allocates the number, forces the creator to the actor and notifies).
 * The whole row runs inside the engine's per-row transaction and without
 * activity log; nothing is notified.
 *
 * The legacy sends the data ready (backend.md section 9): supplier, customer,
 * company and company site already resolved, semantic status, cost type
 * name, creator. What stays here is the qnet-2 side: `old_id` lookups, the
 * company/site coherence, the unique number per company and year (D-2), the
 * column caps, the amounts, the header work order shared by the lines and the
 * derived receipts (D-5).
 */
class PurchaseOrdersSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const int DESCRIPTION_MAX = 255;

    private const int SECTOR_NOTES_MAX = 500;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyPurchaseOrderLineWriter $lineWriter,
        private readonly LegacyDocumentNumberer $numberer,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'purchase-orders';
    }

    public function label(): string
    {
        return 'Purchase orders';
    }

    public function endpoint(): string
    {
        return 'purchase-orders';
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
            MigrationDependency::optional('operational-sites'),
            MigrationDependency::optional('payment-methods'),
            MigrationDependency::optional('vat-rates'),
            MigrationDependency::optional('work-orders'),
            MigrationDependency::optional('purchase-requests'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'number', 'label' => 'Number', 'type' => 'number'],
            ['id' => 'order_date', 'label' => 'Order date', 'type' => 'date'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id)', 'type' => 'number'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'status', 'label' => 'Status', 'type' => 'string'],
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

        if ($this->existsByOldId(PurchaseOrder::class, $externalId)) {
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
        $status = PurchaseOrderStatus::tryFrom((string) ($record['status'] ?? ''))
            ?? throw new RuntimeException("Status '".($record['status'] ?? '')."' is not valid.");

        // Step 1: the required anchors and the creator; any not migrated fails the row (D-4).
        $anchors = [
            ...$this->companyAndSite($record),
            'supplier_id' => $this->requiredOldId(Registry::class, $record['supplier_id'] ?? null, 'Supplier'),
        ];
        $orderDate = CarbonImmutable::parse($this->requiredText($record['order_date'] ?? null, 'order_date'));

        // Step 2: the header, quietly, with a number unique per company and year (D-2).
        $order = new PurchaseOrder([
            ...$anchors,
            'order_date' => $orderDate->toDateString(),
            'description' => $this->cappedText($this->requiredText($record['description'] ?? null, 'description'), self::DESCRIPTION_MAX, 'Description', $warnings),
            'general_notes' => $this->blankToNull($record['general_notes'] ?? null),
            'payment_method_id' => $this->remapLegacyId(PaymentMethod::class, $record['payment_method_id'] ?? null, 'payment_method_id', $warnings),
            'is_internal_user' => (bool) ($record['is_internal_user'] ?? false),
            'customer_id' => $this->remapLegacyId(Registry::class, $record['customer_id'] ?? null, 'customer_id', $warnings),
            'operational_site_id' => $this->remapLegacyId(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings),
            'sector_notes' => $this->cappedText($record['sector_notes'] ?? null, self::SECTOR_NOTES_MAX, 'Sector notes', $warnings),
        ]);
        $order->forceFill([
            ...$this->number($anchors['company_id'], $orderDate->year, $record, $order->general_notes, $warnings),
            'year' => $orderDate->year,
            'status' => $status,
            'created_by' => $this->requiredOldId(User::class, $record['creator_user_id'] ?? null, 'Creator'),
        ])->saveQuietly();

        // Step 3: lines, totals and the RDA lines now held by the order.
        $this->lineWriter->write($order, (array) ($record['lines'] ?? []), $warnings);

        // Step 4: the receipts derived from the legacy status (D-5).
        $this->receipts($order, $status, $record, $warnings);

        // Step 5: the work order shared by every line, then the legacy anchors.
        $this->finalizeLegacyRecord($order, $externalId, $record, ['work_order_id' => $this->sharedWorkOrderId($order)]);

        return MigrationRowOutcome::created($warnings, $order);
    }

    /**
     * The legacy number when (company, year) does not have it yet; otherwise
     * the first number past both the highest legacy number of that year and
     * the highest already in qnet-2, with the legacy reference in the notes.
     * The sequence never falls behind an imported number (D-2).
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{number: int, general_notes: string|null}
     */
    private function number(int $companyId, int $year, array $record, ?string $notes, array &$warnings): array
    {
        $legacyNumber = (int) ($record['number'] ?? 0);
        $assigned = $this->numberer->number(PurchaseOrder::class, PurchaseOrderNumberSequence::class, $companyId, $year, $legacyNumber, (int) ($record['year_max_number'] ?? 0));

        if ($assigned['reassigned']) {
            $reference = "Numero legacy {$legacyNumber}".(filled($record['protocol'] ?? null) ? " (protocollo {$record['protocol']})" : '');
            $notes = $notes === null ? $reference : "{$notes}\n\n{$reference}";
            $warnings[] = "Number {$legacyNumber}/{$year} already used: the order takes {$assigned['number']}/{$year}.";
        }

        return ['number' => $assigned['number'], 'general_notes' => $notes];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function receipts(PurchaseOrder $order, PurchaseOrderStatus $status, array $record, array &$warnings): void
    {
        if ($status === PurchaseOrderStatus::PartiallyFulfilled) {
            $warnings[] = 'Partially fulfilled in the legacy: no receipt is imported.';

            return;
        }

        if ($status === PurchaseOrderStatus::Fulfilled) {
            $receiptDate = $this->legacyTimestamp($record['updated_at'] ?? null) ?? CarbonImmutable::parse($order->order_date);
            $this->lineWriter->receiveAll($order, $receiptDate->toDateString());
        }
    }

    /**
     * The work order of every line when they all share one, as the generation from RDA does.
     */
    private function sharedWorkOrderId(PurchaseOrder $order): ?int
    {
        $workOrderIds = $order->lines()->pluck('work_order_id')->unique()->values();

        return $workOrderIds->count() === 1 && $workOrderIds->first() !== null ? (int) $workOrderIds->first() : null;
    }
}
