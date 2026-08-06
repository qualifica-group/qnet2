<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * Validated payload for creating an opportunity (POST /api/opportunities,
 * spec 0040). `registryId` is the only mandatory scalar (D-4); every other
 * relation is optional. `managerSlots` is ORDERED and gap-aware,
 * mirroring CreateRegistryData verbatim: index+1 is the manager's static
 * "G.A. n" position, a null entry an intentionally empty slot — synced by
 * OpportunityService post-create via `->sync()`, so it is NOT a mass-
 * assignable column and stays out of attributes().
 *
 * With `leadId` set, OpportunityService overwrites the BR-1-derivable
 * attributes with LeadOpportunityDefaultsResolver's values (the FormRequest
 * already rejected a conflicting submission as `prohibited`).
 *
 * Amendment rev.3: `businessFunctionId`/`productCategoryId` are REPLACED by
 * `productLines` — a to-many collection, delete-all + insert synced
 * separately by OpportunityService (like `managerSlots`), so it also stays
 * out of attributes(). User directive 2026-07-17: `companyId`/
 * `companySiteId` are REMOVED entirely.
 * Spec 0082: `opportunityStatusId` is REMOVED — the Opportunity's status is
 * computed from its quotes (App\Services\Opportunities\OpportunityStatusResolver),
 * never stored nor submitted.
 *
 * Spec 0056: `operationalSiteId` is reintroduced as a plain, optional scalar
 * — appended AT THE END with a default (never inserted positionally: this
 * constructor mixes required params with no default and later ones that
 * have one, so a middle insertion would be an ArgumentCountError trap, the
 * same one already paid on the projects/campaigns precedent).
 *
 * Spec 0047: `stateId` (Regione, D1) is a plain editable scalar — inherited
 * from the lead (LeadOpportunityDefaultsResolver/ConvertLeadToOpportunity)
 * or submitted directly on a standalone create — part of attributes().
 * Spec 0083, D-2: `workflowStatusId` is REMOVED — the Opportunity carries no
 * working-state override any more, the configurator having moved onto the
 * Offerta (-> Quote).
 *
 * Spec 0057, D-5: `name` is REMOVED entirely — it is no longer a client
 * input anywhere (form or request-management create). OpportunityService
 * derives it as `OPP_{id}` right after the insert, mirroring
 * RegistryService's own placeholder-then-derive pattern for `registries.name`.
 *
 * User directive 2026-07-27: `generalNotes` ("Note generali") is a plain
 * nullable scalar, prefilled from the lead's own `notes` at conversion but
 * never BR-1-locked — appended AT THE END for the same ArgumentCountError
 * reason as `operationalSiteId`.
 *
 * Spec 0059: `rewards` — the reward-type ids to assign, synced by
 * RewardAssignmentWriter — follows the SAME null-means-untouched/out-of-
 * attributes() convention as `productsOfInterest` (D-3: the beneficiary is
 * always the opportunity's `reporterId`, never part of this collection).
 *
 * User directive 2026-08-05 ("informazioni aggiuntive anche sul form
 * opportunita', come in gestione richieste"): `attributeValues` is the
 * submitted dynamic-field map, `null` when the key was absent. Out of
 * attributes() like every collection above — the column is not fillable and
 * the map is validated/merged by RequestAttributeValueWriter AFTER the
 * product lines are synced, i.e. against the applicable set those lines
 * produce (the same set the form rendered its fields from).
 */
final readonly class CreateOpportunityData
{
    /**
     * @param  array<int, int|null>|null  $managerSlots
     * @param  array<int, array{business_function_id: int, product_category_id: int}>|null  $productLines
     * @param  array<int, int>|null  $productsOfInterest  "prodotti di interesse" (user directive 2026-07-22): a to-many reference synced by OpportunityProductInterestWriter, never mass-assigned — out of attributes() like the two collections above
     * @param  array<int, int>|null  $rewards  reward-type ids (spec 0059), synced by RewardAssignmentWriter — out of attributes() like the collection above
     */
    public function __construct(
        public ?int $registryId,
        public ?int $referentId,
        public ?int $commercialId,
        public ?int $reporterId,
        public ?int $supervisorId,
        public ?int $sourceId,
        public ?int $leadId,
        public ?array $managerSlots,
        public ?array $productLines,
        public ?string $startDate,
        public ?float $estimatedValue,
        public ?string $expectedCloseDate,
        public ?int $successProbability,
        public ?int $stateId = null,
        public ?array $productsOfInterest = null,
        public ?int $operationalSiteId = null,
        public ?array $rewards = null,
        public ?string $generalNotes = null,
        /** @var array<string, mixed>|null */
        public ?array $attributeValues = null,
    ) {}

    /**
     * Build from the validated StoreOpportunityRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            registryId: isset($data['registry_id']) ? (int) $data['registry_id'] : null,
            referentId: isset($data['referent_id']) ? (int) $data['referent_id'] : null,
            commercialId: isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            reporterId: isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            supervisorId: isset($data['supervisor_id']) ? (int) $data['supervisor_id'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            managerSlots: array_key_exists('manager_slots', $data)
                ? array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $data['manager_slots'])
                : null,
            productLines: array_key_exists('product_lines', $data) ? self::normalizeProductLines($data['product_lines']) : null,
            startDate: $data['start_date'] ?? null,
            estimatedValue: isset($data['estimated_value']) ? (float) $data['estimated_value'] : null,
            expectedCloseDate: $data['expected_close_date'] ?? null,
            successProbability: isset($data['success_probability']) ? (int) $data['success_probability'] : null,
            stateId: isset($data['state_id']) ? (int) $data['state_id'] : null,
            productsOfInterest: array_key_exists('products_of_interest', $data) ? self::normalizeIds($data['products_of_interest']) : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            rewards: array_key_exists('rewards', $data) ? self::normalizeRewardTypeIds($data['rewards']) : null,
            generalNotes: $data['general_notes'] ?? null,
            attributeValues: array_key_exists('attribute_values', $data)
                ? (array) $data['attribute_values']
                : null,
        );
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn ($id): int => (int) $id, (array) $ids)));
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeRewardTypeIds(mixed $rows): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['reward_type_id'],
            (array) $rows,
        )));
    }

    public function hasProductsOfInterest(): bool
    {
        return $this->productsOfInterest !== null;
    }

    public function hasRewards(): bool
    {
        return $this->rewards !== null;
    }

    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private static function normalizeProductLines(mixed $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'business_function_id' => (int) $row['business_function_id'],
                'product_category_id' => (int) $row['product_category_id'],
            ],
            (array) $rows,
        );
    }

    public function hasManagerSlots(): bool
    {
        return $this->managerSlots !== null;
    }

    public function hasProductLines(): bool
    {
        return $this->productLines !== null;
    }

    /**
     * The opportunity's own scalar attributes for a mass-assignment create
     * (framework array boundary). `managerSlots`/`productLines` are NOT
     * included: they are to-many references synced separately by
     * OpportunityService. `name` is NOT included either (spec 0057, D-5): it
     * is derived post-insert by OpportunityService, never mass-assigned here.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'registry_id' => $this->registryId,
            'referent_id' => $this->referentId,
            'commercial_id' => $this->commercialId,
            'reporter_id' => $this->reporterId,
            'supervisor_id' => $this->supervisorId,
            'source_id' => $this->sourceId,
            'operational_site_id' => $this->operationalSiteId,
            'lead_id' => $this->leadId,
            'start_date' => $this->startDate,
            'estimated_value' => $this->estimatedValue,
            'expected_close_date' => $this->expectedCloseDate,
            'success_probability' => $this->successProbability,
            'state_id' => $this->stateId,
            'general_notes' => $this->generalNotes,
        ];
    }
}
