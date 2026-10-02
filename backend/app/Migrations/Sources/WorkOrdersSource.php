<?php

namespace App\Migrations\Sources;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\QuoteLineType;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\MapsLegacyWorkOrderFields;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\QuoteLineDuplicator;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * `work-orders` migration source (spec 0189): the legacy `orders`, created
 * through WorkOrderService::create() under the code `COM-{legacy id}` (G-5),
 * then tagged with `old_id` and the legacy timestamps. The parent offer
 * (`quotes.old_id`) is mandatory: unresolved, the row fails.
 *
 * No task template (G-2): no stage, task or email is generated, and the
 * service notifies nobody on create. Lines (G-10) are the offer's REVENUE
 * lines matched on `quote_lines.old_id`; a line already programmed into
 * another commessa stays with it and this one gets a copy of the line on the
 * offer (QuoteLineDuplicator, user decision 2026-10-02), never reaching
 * WorkOrderLineWriter's 422. Closed legacy statuses become a forced
 * close with a legacy reason. The whole row runs without activity log (G-3).
 */
class WorkOrdersSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;
    use MapsLegacyWorkOrderFields;

    private const string CODE_FORMAT = 'COM-%04d';

    public function __construct(
        ExternalApiClient $client,
        private readonly WorkOrderService $service,
        private readonly QuoteLineDuplicator $lineDuplicator,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'work-orders';
    }

    public function label(): string
    {
        return 'Work orders';
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
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'quote_id', 'label' => 'Quote (external id)', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'type', 'label' => 'Type', 'type' => 'string'],
            ['id' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
            ['id' => 'end_date', 'label' => 'End date', 'type' => 'date'],
            ['id' => 'status_label', 'label' => 'Status', 'type' => 'string'],
            ['id' => 'quote_line_ids', 'label' => 'Quote lines (external ids)', 'type' => 'string'],
            ['id' => 'supervisor_user_ids', 'label' => 'Supervisors (external ids)', 'type' => 'string'],
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

        if ($this->existsByOldId(WorkOrder::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [];

        // Step 1: the mandatory parent offer.
        $quoteId = $this->resolveQuote($record['quote_id'] ?? null);

        // Step 2: create through the domain service, without task template (G-2).
        $workOrder = $this->service->create($this->buildData($externalId, $quoteId, $record, $warnings));

        // Step 3: old_id and the legacy timestamps.
        $this->finalizeLegacyRecord($workOrder, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $workOrder);
    }

    private function resolveQuote(mixed $externalQuoteId): int
    {
        $quoteId = ($externalQuoteId === null || $externalQuoteId === '')
            ? null
            : $this->resolveOldId(Quote::class, $externalQuoteId);

        if ($quoteId === null) {
            throw new RuntimeException("Unresolved quote_id (legacy id {$externalQuoteId}); migrate quotes first.");
        }

        return $quoteId;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function buildData(int|string $externalId, int $quoteId, array $record, array &$warnings): CreateWorkOrderData
    {
        $forceCloseReason = $this->forceCloseReason($record['status'] ?? null, $warnings);

        return new CreateWorkOrderData(
            code: $this->legacyCode($externalId, $warnings),
            quoteId: $quoteId,
            title: $this->legacyTitle($externalId, $record['title'] ?? null, $warnings),
            type: $this->legacyType($record['type'] ?? null, $warnings),
            startDate: $this->startDate($record, $warnings),
            callbackDate: $this->legacyTimestamp($record['callback_date'] ?? null)?->toDateString(),
            description: $this->blankToNull($record['description'] ?? null),
            internalNotes: $this->internalNotes($record),
            isForceClosed: $forceCloseReason !== null,
            forceCloseReason: $forceCloseReason,
            quoteLineIds: $this->legacyQuoteLineIds($quoteId, $record['quote_line_ids'] ?? [], $warnings),
            supervisorIds: $this->remapLegacyIds(User::class, $record['supervisor_user_ids'] ?? [], 'supervisor_user_ids', $warnings),
            participantSlots: $this->legacyManagerSlots($record['participant_user_ids'] ?? [], $warnings, 'participant_user_ids'),
        );
    }

    /**
     * `COM-{legacy id}` (G-5); already taken -> the service's next sequential
     * code, with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function legacyCode(int|string $externalId, array &$warnings): ?string
    {
        $code = sprintf(self::CODE_FORMAT, (int) $externalId);

        if (! WorkOrder::query()->where('code', $code)->exists()) {
            return $code;
        }

        $warnings[] = "Code {$code} already taken; a sequential code was generated.";

        return null;
    }

    /**
     * The offer's REVENUE lines matching the legacy line ids (G-10). A line
     * already programmed into another commessa stays there ("una riga, una
     * sola Commessa"): this commessa gets a copy of it on the offer instead,
     * with a warning (the offer total grows by the copy).
     *
     * @param  array<int, string>  $warnings
     * @return array<int, int>
     */
    private function legacyQuoteLineIds(int $quoteId, mixed $externalLineIds, array &$warnings): array
    {
        $externalLineIds = array_values(array_unique(array_map(intval(...), (array) ($externalLineIds ?? []))));

        if ($externalLineIds === []) {
            return [];
        }

        $lineIdsByOldId = QuoteLine::query()
            ->where('quote_id', $quoteId)
            ->where('line_type', QuoteLineType::Revenue)
            ->whereIn('old_id', $externalLineIds)
            ->pluck('id', 'old_id');

        foreach (array_diff($externalLineIds, $lineIdsByOldId->keys()->all()) as $missing) {
            $warnings[] = "Unresolved quote line (legacy id {$missing}) on this offer; not linked.";
        }

        $programmed = DB::table('quote_line_work_order')
            ->whereIn('quote_line_id', $lineIdsByOldId->values())
            ->pluck('quote_line_id')
            ->map(intval(...))
            ->all();

        $lineIds = [];

        foreach ($lineIdsByOldId as $oldId => $lineId) {
            if (! in_array($lineId, $programmed, true)) {
                $lineIds[] = $lineId;

                continue;
            }

            $copy = $this->lineDuplicator->duplicate(QuoteLine::query()->with('commissions', 'quote')->findOrFail($lineId));
            $lineIds[] = $copy->id;
            $warnings[] = "Quote line (legacy id {$oldId}) already belongs to another work order; duplicated on the offer (+{$copy->net_amount} net).";
        }

        return $lineIds;
    }
}
