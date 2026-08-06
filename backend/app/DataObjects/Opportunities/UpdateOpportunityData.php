<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * Validated payload for a partial (PATCH) opportunity update
 * (PUT/PATCH /api/opportunities/{opportunity}, spec 0040). Every scalar is a
 * legitimately nullable VALUE, so the `*Submitted` flags carry the "was this
 * key actually present" distinction a plain property cannot express
 * (mirrors UpdateLeadData). `managerSlots`/`productLines` follow
 * CreateRegistryData/UpdateRegistryData's simpler convention instead: null
 * means "not submitted, leave untouched", an array (including empty) is an
 * authoritative sync.
 *
 * `leadId` is deliberately ABSENT: `lead_id` is `prohibited` on update
 * (BR-2, immutable), so it never reaches this DTO. Every BR-2-locked field
 * that IS submitted has already been validated (UpdateOpportunityRequest) to
 * either match its current derived value or be absent — no extra
 * enforcement is needed here.
 *
 * Amendment rev.3: `businessFunctionId`/`productCategoryId` are REPLACED by
 * `productLines`. User directive 2026-07-17: `companyId`/`companySiteId`
 * are REMOVED entirely. Spec 0082: `opportunityStatusId` is REMOVED too — the
 * status is computed from the quotes, never stored nor submitted.
 *
 * Spec 0056: `operationalSiteId` is reintroduced as a plain, optional scalar,
 * following the SAME `*Submitted` convention as every other unlocked FK
 * (`sourceId`, etc.) — the pair is what lets a PATCH AZZERARE the field
 * (AC-004), appended at the very end of the constructor for the same
 * ArgumentCountError reason as CreateOpportunityData.
 *
 * Spec 0047: `stateId` (Regione, D1) follows the same `*Submitted`
 * convention as every other plain scalar. Spec 0083, D-2: `workflowStatusId`
 * is REMOVED — the Opportunity carries no working-state override any more,
 * the configurator having moved onto the Offerta (-> Quote).
 *
 * Spec 0057, D-5: `name` is REMOVED entirely — it is immutable server-side
 * (derived once at create as `OPP_{id}`), never part of a PATCH payload.
 *
 * User directive 2026-07-27: `generalNotes` ("Note generali") follows the
 * same `*Submitted` convention as every other plain nullable scalar — the
 * pair is what lets a PATCH clear the field.
 *
 * Spec 0059: `rewards` follows the SAME null-means-untouched convention as
 * `productsOfInterest`, but — UNLIKE it — CAN be cleared to `[]` (AC-020):
 * an opportunity is allowed to carry zero rewards, so its FormRequest rule
 * has no `min:1`. Synced by RewardAssignmentWriter.
 *
 * Spec 0084, D-1: the former `attributeValues` field (user directive
 * 2026-08-05) is REMOVED — see UpdateQuoteData for its replacement.
 */
final readonly class UpdateOpportunityData
{
    /**
     * @param  array<int, int|null>|null  $managerSlots
     * @param  array<int, array{business_function_id: int, product_category_id: int}>|null  $productLines
     * @param  array<int, int>|null  $productsOfInterest  "prodotti di interesse" (user directive 2026-07-22): same null-means-untouched convention as the two collections above, synced by OpportunityProductInterestWriter
     * @param  array<int, int>|null  $rewards  reward-type ids (spec 0059), synced by RewardAssignmentWriter — same null-means-untouched convention, `[]` clears every assignment
     */
    public function __construct(
        public ?int $registryId = null,
        public bool $registryIdSubmitted = false,
        public ?int $referentId = null,
        public bool $referentIdSubmitted = false,
        public ?int $commercialId = null,
        public bool $commercialIdSubmitted = false,
        public ?int $reporterId = null,
        public bool $reporterIdSubmitted = false,
        public ?int $supervisorId = null,
        public bool $supervisorIdSubmitted = false,
        public ?int $sourceId = null,
        public bool $sourceIdSubmitted = false,
        public ?array $managerSlots = null,
        public ?array $productLines = null,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?float $estimatedValue = null,
        public bool $estimatedValueSubmitted = false,
        public ?string $expectedCloseDate = null,
        public bool $expectedCloseDateSubmitted = false,
        public ?int $successProbability = null,
        public bool $successProbabilitySubmitted = false,
        public ?int $stateId = null,
        public bool $stateIdSubmitted = false,
        public ?array $productsOfInterest = null,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
        public ?array $rewards = null,
        public ?string $generalNotes = null,
        public bool $generalNotesSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateOpportunityRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            registryId: self::nullableInt($data, 'registry_id'),
            registryIdSubmitted: array_key_exists('registry_id', $data),
            referentId: self::nullableInt($data, 'referent_id'),
            referentIdSubmitted: array_key_exists('referent_id', $data),
            commercialId: self::nullableInt($data, 'commercial_id'),
            commercialIdSubmitted: array_key_exists('commercial_id', $data),
            reporterId: self::nullableInt($data, 'reporter_id'),
            reporterIdSubmitted: array_key_exists('reporter_id', $data),
            supervisorId: self::nullableInt($data, 'supervisor_id'),
            supervisorIdSubmitted: array_key_exists('supervisor_id', $data),
            sourceId: self::nullableInt($data, 'source_id'),
            sourceIdSubmitted: array_key_exists('source_id', $data),
            managerSlots: array_key_exists('manager_slots', $data)
                ? array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $data['manager_slots'])
                : null,
            productLines: array_key_exists('product_lines', $data) ? self::normalizeProductLines($data['product_lines']) : null,
            startDate: array_key_exists('start_date', $data) ? $data['start_date'] : null,
            startDateSubmitted: array_key_exists('start_date', $data),
            estimatedValue: array_key_exists('estimated_value', $data) && $data['estimated_value'] !== null ? (float) $data['estimated_value'] : null,
            estimatedValueSubmitted: array_key_exists('estimated_value', $data),
            expectedCloseDate: array_key_exists('expected_close_date', $data) ? $data['expected_close_date'] : null,
            expectedCloseDateSubmitted: array_key_exists('expected_close_date', $data),
            successProbability: self::nullableInt($data, 'success_probability'),
            successProbabilitySubmitted: array_key_exists('success_probability', $data),
            stateId: self::nullableInt($data, 'state_id'),
            stateIdSubmitted: array_key_exists('state_id', $data),
            productsOfInterest: array_key_exists('products_of_interest', $data) ? self::normalizeIds($data['products_of_interest']) : null,
            operationalSiteId: self::nullableInt($data, 'operational_site_id'),
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
            rewards: array_key_exists('rewards', $data) ? self::normalizeRewardTypeIds($data['rewards']) : null,
            generalNotes: array_key_exists('general_notes', $data) ? $data['general_notes'] : null,
            generalNotesSubmitted: array_key_exists('general_notes', $data),
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

    public function hasManagerSlots(): bool
    {
        return $this->managerSlots !== null;
    }

    public function hasProductLines(): bool
    {
        return $this->productLines !== null;
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

    /**
     * Only the attributes the client actually submitted, ready for a
     * partial mass-assignment update. `managerSlots`/`productLines` are
     * never included: they are synced separately by OpportunityService.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->registryIdSubmitted) {
            $attributes['registry_id'] = $this->registryId;
        }

        if ($this->referentIdSubmitted) {
            $attributes['referent_id'] = $this->referentId;
        }

        if ($this->commercialIdSubmitted) {
            $attributes['commercial_id'] = $this->commercialId;
        }

        if ($this->reporterIdSubmitted) {
            $attributes['reporter_id'] = $this->reporterId;
        }

        if ($this->supervisorIdSubmitted) {
            $attributes['supervisor_id'] = $this->supervisorId;
        }

        if ($this->sourceIdSubmitted) {
            $attributes['source_id'] = $this->sourceId;
        }

        if ($this->operationalSiteIdSubmitted) {
            $attributes['operational_site_id'] = $this->operationalSiteId;
        }

        if ($this->startDateSubmitted) {
            $attributes['start_date'] = $this->startDate;
        }

        if ($this->estimatedValueSubmitted) {
            $attributes['estimated_value'] = $this->estimatedValue;
        }

        if ($this->expectedCloseDateSubmitted) {
            $attributes['expected_close_date'] = $this->expectedCloseDate;
        }

        if ($this->successProbabilitySubmitted) {
            $attributes['success_probability'] = $this->successProbability;
        }

        if ($this->stateIdSubmitted) {
            $attributes['state_id'] = $this->stateId;
        }

        if ($this->generalNotesSubmitted) {
            $attributes['general_notes'] = $this->generalNotes;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null ? (int) $data[$key] : null;
    }
}
