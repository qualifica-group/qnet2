<?php

namespace App\Migrations\Sources;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\MapsLegacyQuoteLines;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyQuoteStatusApplier;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\User;
use App\Services\Quotes\QuoteManagerInheritance;
use App\Services\Quotes\QuoteManagerSyncMode;
use App\Services\Quotes\QuoteManagerWriter;
use App\Services\QuoteService;
use RuntimeException;

/**
 * `quotes` migration source (spec 0189): the legacy `quotations` with their
 * `quotationservices` lines, created through QuoteService::create() under
 * the code `QUO-{legacy id}` (G-5), then tagged with `old_id` and the legacy
 * timestamps. The parent opportunity (`opportunities.old_id`) is mandatory:
 * unresolved, the row fails.
 *
 * Nobody is notified (G-2): the service receives an empty manager set, the
 * legacy managers are written afterwards through QuoteManagerWriter (which
 * notifies no one; promotion keeps them Gestori Account of the opportunity).
 * The status (G-6) and the contract (G-7) are applied afterwards by
 * LegacyQuoteStatusApplier, quietly. The whole row runs without activity log
 * (G-3).
 */
class QuotesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;
    use MapsLegacyQuoteLines;

    private const string CODE_FORMAT = QuoteService::CODE_PREFIX.'-%04d';

    private const int TITLE_MAX = 191;

    public function __construct(
        ExternalApiClient $client,
        private readonly QuoteService $service,
        private readonly QuoteManagerWriter $managerWriter,
        private readonly QuoteManagerInheritance $managerInheritance,
        private readonly QuoteManagerSyncMode $managerSyncMode,
        private readonly LegacyQuoteStatusApplier $statusApplier,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'quotes';
    }

    public function label(): string
    {
        return 'Quotes';
    }

    public function endpoint(): string
    {
        return 'quotes';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'opportunity_id', 'label' => 'Opportunity (external id)', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'quote_date', 'label' => 'Quote date', 'type' => 'date'],
            ['id' => 'status', 'label' => 'Status', 'type' => 'number'],
            ['id' => 'accepted_at', 'label' => 'Accepted at', 'type' => 'date'],
            ['id' => 'manager_user_ids', 'label' => 'Managers (external ids)', 'type' => 'string'],
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

        if ($this->existsByOldId(Quote::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($context, $externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(MigrationImportContext $context, int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [];

        // Step 1: the mandatory parent and the importable revenue lines.
        $opportunity = $this->resolveOpportunity($record['opportunity_id'] ?? null);
        [$lines, $legacyLineIds] = $this->legacyOfferLines($record['lines'] ?? [], $warnings);

        // Step 2: the managers to write after the create (read before it, see managerSlotsToWrite()).
        $managerSlots = $this->managerSlotsToWrite($opportunity, $record, $warnings);

        // Step 3: create through the domain service, without managers nor status (G-2/G-6),
        // then write the managers, which notifies nobody.
        $quote = $this->service->create($this->buildData($externalId, $opportunity, $record, $lines, $warnings), $context->actor);

        if ($managerSlots !== []) {
            $this->managerWriter->sync($quote, $managerSlots, promoteToOpportunity: true);
        }

        // Step 4: anchor each created line to its legacy line.
        $this->tagLegacyLines($quote, $legacyLineIds);

        // Step 5: legacy status and contract, then old_id and timestamps.
        $this->statusApplier->apply($quote, (int) ($record['status'] ?? 0), [
            'accepted_at' => $this->legacyTimestamp($record['accepted_at'] ?? null),
            'validated_at' => $this->legacyTimestamp($record['validated_at'] ?? null),
            'renewal_date' => $this->legacyTimestamp($record['renewal_date'] ?? null),
            'declined_at' => $this->legacyTimestamp($record['declined_at'] ?? null),
        ], $warnings);
        $this->finalizeLegacyRecord($quote, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $quote);
    }

    private function resolveOpportunity(mixed $externalOpportunityId): Opportunity
    {
        $opportunity = ($externalOpportunityId === null || $externalOpportunityId === '')
            ? null
            : Opportunity::query()->where('old_id', $externalOpportunityId)->first();

        if ($opportunity === null) {
            throw new RuntimeException("Unresolved opportunity_id (legacy id {$externalOpportunityId}); migrate opportunities first.");
        }

        return $opportunity;
    }

    /**
     * The commercial roles are always submitted (never inherited): the legacy
     * value wins, even empty. The operational site is submitted only when it
     * resolves, otherwise the opportunity's own is inherited.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, QuoteLineData>  $lines
     * @param  array<int, string>  $warnings
     */
    private function buildData(int|string $externalId, Opportunity $opportunity, array $record, array $lines, array &$warnings): CreateQuoteData
    {
        $companySite = $this->resolveCompanySite($record['company_site_id'] ?? null, $warnings);
        $operationalSiteId = $this->remapLegacyId(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings);
        $description = trim((string) ($record['description'] ?? ''));

        return new CreateQuoteData(
            code: $this->legacyCode($externalId, $warnings),
            title: $this->legacyTitle($record['title'] ?? null, $warnings),
            opportunityId: $opportunity->id,
            workflowStatusId: null,
            note: null,
            commercialId: $this->remapLegacyId(Referent::class, $record['commercial_referent_id'] ?? null, 'commercial_referent_id', $warnings),
            commercialIdSubmitted: true,
            reporterId: $this->remapLegacyId(Referent::class, $record['reporter_referent_id'] ?? null, 'reporter_referent_id', $warnings),
            reporterIdSubmitted: true,
            supervisorId: $this->remapLegacyId(User::class, $record['supervisor_user_id'] ?? null, 'supervisor_user_id', $warnings),
            supervisorIdSubmitted: true,
            internalNotes: $description !== '' ? $description : null,
            offerLines: $lines,
            companyId: $companySite?->company_id,
            companySiteId: $companySite?->id,
            operationalSiteId: $operationalSiteId,
            operationalSiteIdSubmitted: $operationalSiteId !== null,
            paymentMethodId: $this->remapLegacyId(PaymentMethod::class, $record['payment_method_id'] ?? null, 'payment_method_id', $warnings),
            managerSlots: [],
        );
    }

    /**
     * `QUO-{legacy id}` (G-5); already taken -> the service's next sequential
     * code, with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function legacyCode(int|string $externalId, array &$warnings): ?string
    {
        $code = sprintf(self::CODE_FORMAT, (int) $externalId);

        if (! Quote::query()->where('code', $code)->exists()) {
            return $code;
        }

        $warnings[] = "Code {$code} already taken; a sequential code was generated.";

        return null;
    }

    /**
     * Blank -> null (the automatic title); too long -> truncated with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function legacyTitle(mixed $externalTitle, array &$warnings): ?string
    {
        $title = trim((string) ($externalTitle ?? ''));

        if ($title === '') {
            return null;
        }

        if (mb_strlen($title) > self::TITLE_MAX) {
            $warnings[] = 'Title truncated to '.self::TITLE_MAX.' characters.';

            return mb_substr($title, 0, self::TITLE_MAX);
        }

        return $title;
    }

    /**
     * The company follows its site (ValidatesQuoteCompanySite): both or neither.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveCompanySite(mixed $externalRef, array &$warnings): ?CompanySite
    {
        $id = $this->remapLegacyId(CompanySite::class, $externalRef, 'company_site_id', $warnings);

        return $id === null ? null : CompanySite::query()->find($id, ['id', 'company_id']);
    }

    /**
     * The legacy managers, written after the create through
     * QuoteManagerWriter, which notifies nobody; promotion keeps them Gestori
     * Account of the opportunity (D-6). In synchronized categories the empty
     * create-time set clears the opportunity's own list too: an offer without
     * legacy managers then restores that list, read here before the create,
     * on both sides. Empty = nothing to write.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array<int, int|null>
     */
    private function managerSlotsToWrite(Opportunity $opportunity, array $record, array &$warnings): array
    {
        $slots = $this->legacyManagerSlots($record['manager_user_ids'] ?? [], $warnings);

        if (array_filter($slots) !== []) {
            return $slots;
        }

        return $this->managerSyncMode->isSynchronized($opportunity)
            ? $this->managerInheritance->fromOpportunity($opportunity)
            : [];
    }
}
