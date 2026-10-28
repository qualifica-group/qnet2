<?php

namespace App\Migrations\Sources;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\AssignsDefaultProductSuppliers;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\MapsLegacyQuoteCostLines;
use App\Migrations\Sources\Concerns\MapsLegacyQuoteLines;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyAttributeValueApplier;
use App\Migrations\Support\LegacyFreeLineProduct;
use App\Migrations\Support\LegacyQuoteState;
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
 * the code `QUO-{legacy id}` (G-5), lines without a service sold as the
 * [OLD] product of their title (spec 0305), then tagged with `old_id` and
 * the legacy timestamps. The parent opportunity (`opportunities.old_id`) is mandatory:
 * unresolved, the row fails.
 *
 * Nobody is notified (G-2): the service receives an empty manager set, the
 * legacy managers are written afterwards through QuoteManagerWriter (which
 * notifies no one; promotion keeps them Gestori Account of the opportunity).
 * The status (G-6, from the legacy `outcome`)
 * and the contract (G-7, the legacy `contract` block) are applied afterwards by LegacyQuoteStatusApplier,
 * quietly. The whole row runs without activity log
 * (G-3). Costs come in as COST lines (G-13); the offer date and the parent
 * opportunity's callback are written as is (G-14); a legacy customer other
 * than the opportunity's is reported, the offer stays under the opportunity
 * (G-15). The legacy "Info preliminari" land in the attribute values
 * applicable to the offer's own lines (G-17). Every line carries the default
 * supplier its product has at migration time, null without one (spec 0320, D-6).
 */
class QuotesSource extends AbstractMigrationSource
{
    use AssignsDefaultProductSuppliers;
    use MapsLegacyOperationalRecord;
    use MapsLegacyQuoteCostLines;
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
        private readonly LegacyAttributeValueApplier $attributeValueApplier,
        private readonly LegacyFreeLineProduct $freeLineProduct,
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

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('opportunities'),
            MigrationDependency::optional('products'),
            MigrationDependency::optional('cost-products'),
            MigrationDependency::optional('product-suppliers'),
            MigrationDependency::optional('product-category-attributes'),
            MigrationDependency::optional('vat-rates'),
            MigrationDependency::optional('payment-methods'),
            MigrationDependency::optional('company-sites'),
            MigrationDependency::optional('operational-sites'),
            MigrationDependency::optional('users'),
            MigrationDependency::optional('referents'),
            MigrationDependency::optional('registries'),
        ];
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
            ['id' => 'outcome', 'label' => 'Outcome', 'type' => 'string'],
            ['id' => 'manager_user_ids', 'label' => 'Managers (external ids)', 'type' => 'string'],
            ['id' => 'lines', 'label' => 'Lines', 'type' => 'number'],
            ['id' => 'cost_lines', 'label' => 'Cost lines', 'type' => 'number'],
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

    /**
     * Walks every legacy record with the import's own paging, for
     * `quotes:realign-legacy-status`.
     *
     * @param  callable(array<string, mixed>): mixed  $handle
     */
    public function eachLegacyRecord(callable $handle): void
    {
        $this->eachRecord($handle);
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

        // Step 1: the mandatory parent, its customer check and the importable lines.
        $opportunity = $this->resolveOpportunity($record['opportunity_id'] ?? null);
        $this->reportCustomerMismatch((bool) ($record['customer_differs_from_opportunity'] ?? false), $warnings);
        [$lines, $legacyLineIds] = $this->legacyOfferLines($record['lines'] ?? [], $warnings);
        $costLines = $this->legacyCostLines($record['cost_lines'] ?? [], $legacyLineIds, $warnings);
        [$lines, $costLines] = $this->withDefaultSuppliers($lines, $costLines, (array) ($record['cost_lines'] ?? []), $warnings);

        // Step 2: the managers to write after the create (read before it, see managerSlotsToWrite()).
        $managerSlots = $this->managerSlotsToWrite($opportunity, $record, $warnings);

        // Step 3: create through the domain service, without managers nor status (G-2/G-6),
        // then write the managers, which notifies nobody.
        $quote = $this->service->create($this->buildData($externalId, $opportunity, $record, $lines, $costLines, $warnings), $context->actor);

        if ($managerSlots !== []) {
            $this->managerWriter->sync($quote, $managerSlots, promoteToOpportunity: true);
        }

        // Step 4: anchor each created line to its legacy line.
        $this->tagLegacyLines($quote, $legacyLineIds);

        // Step 5: legacy status and contract, the additional information (G-17),
        // then old_id, callback and timestamps in the final quiet save.
        $this->statusApplier->apply($quote, LegacyQuoteState::fromRecord($record), $warnings);
        $this->attributeValueApplier->applyToQuote($quote, (array) ($record['attribute_values'] ?? []), $warnings);
        $this->finalizeLegacyRecord($quote, $externalId, $record, [
            'next_callback_at' => $this->legacyTimestamp($record['callback_date'] ?? null)?->startOfDay(),
        ]);

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
     * @param  array<int, QuoteLineData>  $costLines
     * @param  array<int, string>  $warnings
     */
    private function buildData(int|string $externalId, Opportunity $opportunity, array $record, array $lines, array $costLines, array &$warnings): CreateQuoteData
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
            costLines: $costLines,
            companyId: $companySite?->company_id,
            companySiteId: $companySite?->id,
            operationalSiteId: $operationalSiteId,
            operationalSiteIdSubmitted: $operationalSiteId !== null,
            paymentMethodId: $this->remapLegacyId(PaymentMethod::class, $record['payment_method_id'] ?? null, 'payment_method_id', $warnings),
            managerSlots: [],
            quoteDate: $this->legacyTimestamp($record['quote_date'] ?? null)?->toDateString(),
        );
    }

    /**
     * An offer cannot have a customer other than its opportunity's (G-15):
     * the legacy one, when different (flagged by the legacy), is only reported.
     *
     * @param  array<int, string>  $warnings
     */
    private function reportCustomerMismatch(bool $differs, array &$warnings): void
    {
        if ($differs) {
            $warnings[] = "Legacy customer differs from the opportunity's; the offer stays under the opportunity's customer.";
        }
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
