<?php

declare(strict_types=1);

namespace App\Migrations\Sources;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestStatus;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\RequiresLegacyAnchors;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyPurchaseRequestLineWriter;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\PurchaseRequestPriority;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use RuntimeException;

/**
 * `purchase-requests` migration source (spec 0294): the legacy RDA with their
 * lines and the status history of the lines, written DIRECTLY to the models,
 * not through PurchaseRequestService::create() (it forces the creator to the
 * actor, makes every line pending and notifies). The whole row runs inside the
 * engine's per-row transaction and without activity log; nothing is notified.
 *
 * The legacy sends the data ready (backend.md section 9): customer, creator,
 * company and company site already resolved (D-1), priority as a name, unit as
 * a qnet-2 code, semantic statuses. What stays here is the qnet-2 side:
 * `old_id` lookups, the company/site coherence, the column caps, the amounts
 * and the closure rule of a request whose lines are all terminal (D-2).
 */
class PurchaseRequestsSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord, RequiresLegacyAnchors;

    private const int SUBJECT_MAX = 255;

    /** @var array{byName: array<string, int>, default: int|null}|null */
    private ?array $priorities = null;

    public function __construct(
        ExternalApiClient $client,
        private readonly LegacyPurchaseRequestLineWriter $lineWriter,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'purchase-requests';
    }

    public function label(): string
    {
        return 'Purchase requests';
    }

    public function endpoint(): string
    {
        return 'purchase-requests';
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
            MigrationDependency::required('operational-sites'),
            MigrationDependency::required('business-functions'),
            MigrationDependency::required('work-orders'),
            MigrationDependency::optional('registries'),
            MigrationDependency::optional('vat-rates'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'subject', 'label' => 'Subject', 'type' => 'string'],
            ['id' => 'requested_at', 'label' => 'Requested at', 'type' => 'date'],
            ['id' => 'priority_name', 'label' => 'Priority', 'type' => 'string'],
            ['id' => 'requester_user_id', 'label' => 'Requester (external id)', 'type' => 'number'],
            ['id' => 'function_manager_user_id', 'label' => 'Function manager (external id)', 'type' => 'number'],
            ['id' => 'work_order_id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'customer_id', 'label' => 'Customer (external id)', 'type' => 'number'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'is_closed', 'label' => 'Closed', 'type' => 'boolean'],
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

        if ($this->existsByOldId(PurchaseRequest::class, $externalId)) {
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

        // Step 1: the people and the anchors; any not migrated fails the row (D-6).
        $anchors = $this->anchors($record);

        // Step 2: the header, quietly, with the legacy creator.
        $purchaseRequest = new PurchaseRequest([
            ...$anchors,
            'subject' => $this->cappedText($this->requiredText($record['subject'] ?? null, 'subject'), self::SUBJECT_MAX, 'Subject', $warnings),
            'requested_at' => $this->requiredText($record['requested_at'] ?? null, 'requested_at'),
            'purchase_request_priority_id' => $this->priorityId($record['priority_name'] ?? null, $warnings),
            'customer_id' => $this->remapLegacyId(Registry::class, $record['customer_id'] ?? null, 'customer_id', $warnings),
            'notes' => $this->blankToNull($record['notes'] ?? null),
            'delivery_terms' => $this->blankToNull($record['delivery_terms'] ?? null),
            'procurement_plan' => $this->blankToNull($record['procurement_plan'] ?? null),
            'technical_requirements' => $this->blankToNull($record['technical_requirements'] ?? null),
            'special_conditions' => $this->blankToNull($record['special_conditions'] ?? null),
        ]);
        $purchaseRequest->forceFill([
            'created_by' => $this->requiredOldId(User::class, $record['creator_user_id'] ?? null, 'Creator'),
            'status' => PurchaseRequestStatus::Open,
        ])->saveQuietly();

        // Step 3: lines, status history and totals.
        $this->lineWriter->write($purchaseRequest, (array) ($record['lines'] ?? []), $warnings);

        // Step 4: the closure, legacy or by the qnet-2 rule (D-2), then the legacy anchors.
        $this->finalizeLegacyRecord($purchaseRequest, $externalId, $record, $this->closure($purchaseRequest, $record, $warnings));

        return MigrationRowOutcome::created($warnings, $purchaseRequest);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, int|null>
     *
     * @throws RuntimeException when a required reference is missing, not migrated or incoherent
     */
    private function anchors(array $record): array
    {
        $workOrderId = $record['work_order_id'] ?? null;

        return [
            ...$this->companyAndSite($record),
            'requester_id' => $this->requiredOldId(User::class, $record['requester_user_id'] ?? null, 'Requester'),
            'function_manager_id' => $this->requiredOldId(User::class, $record['function_manager_user_id'] ?? null, 'Function manager'),
            'operational_site_id' => $this->requiredOldId(OperationalSite::class, $record['operational_site_id'] ?? null, 'Operational site'),
            'business_function_id' => $this->requiredOldId(BusinessFunction::class, $record['business_function_id'] ?? null, 'Business function'),
            'work_order_id' => $workOrderId === null ? null : $this->requiredOldId(WorkOrder::class, $workOrderId, 'Work order'),
        ];
    }

    /**
     * The closure columns (D-2): the legacy one when closed there; otherwise,
     * with every line terminal, the author and date of the last status change
     * imported (no change: the legacy `updated_at`, no author), no reason, as
     * the native automatic closure.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function closure(PurchaseRequest $purchaseRequest, array $record, array &$warnings): array
    {
        if ((bool) ($record['is_closed'] ?? false)) {
            return [
                'status' => PurchaseRequestStatus::Closed,
                'closed_by' => $this->remapLegacyId(User::class, $record['closed_by_user_id'] ?? null, 'closed_by_user_id', $warnings),
                'closed_at' => $this->legacyTimestamp($record['closed_at'] ?? null) ?? $this->legacyTimestamp($record['updated_at'] ?? null),
                'close_reason' => $this->blankToNull($record['close_reason'] ?? null),
            ];
        }

        $statuses = $purchaseRequest->lines()->pluck('status');

        if ($statuses->isEmpty() || $statuses->contains(fn (PurchaseRequestLineStatus $status): bool => ! $status->isTerminal())) {
            return [];
        }

        $warnings[] = 'Closed at import: every line is in a terminal state.';
        $lastChange = PurchaseRequestLineStatusLog::query()
            ->whereIn('purchase_request_line_id', $purchaseRequest->lines()->select('id'))
            ->latest('created_at')
            ->latest('id')
            ->first(['user_id', 'created_at']);

        return [
            'status' => PurchaseRequestStatus::Closed,
            'closed_by' => $lastChange?->user_id,
            'closed_at' => $lastChange?->created_at ?? $this->legacyTimestamp($record['updated_at'] ?? null),
            'close_reason' => null,
        ];
    }

    /**
     * The priority by name (trim, case-insensitive), else the default one with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function priorityId(mixed $name, array &$warnings): int
    {
        $this->priorities ??= $this->priorityIndex();
        $name = $this->blankToNull($name);
        $id = $name === null ? null : ($this->priorities['byName'][mb_strtolower($name)] ?? null);

        if ($id !== null) {
            return $id;
        }

        $warnings[] = "Priority '{$name}' not found: the default priority is used.";

        return $this->priorities['default'] ?? throw new RuntimeException('No purchase request priority configured.');
    }

    /**
     * @return array{byName: array<string, int>, default: int|null}
     */
    private function priorityIndex(): array
    {
        $priorities = PurchaseRequestPriority::query()->orderByDesc('is_default')->orderBy('sort_order')->get(['id', 'name']);

        return [
            'byName' => $priorities->mapWithKeys(fn (PurchaseRequestPriority $priority): array => [mb_strtolower(trim($priority->name)) => $priority->id])->all(),
            'default' => $priorities->first()?->id,
        ];
    }
}
