<?php

declare(strict_types=1);

namespace App\DataObjects\Quotes;

/**
 * Validated payload for a partial (PATCH) quote update
 * (PUT/PATCH /api/quotes/{quote}, spec 0065; spec 0083 T-04 for
 * `workflowStatusId`/`note`). Every scalar is a legitimately nullable VALUE,
 * so the `*Submitted` flags carry the "was this key actually present"
 * distinction a plain property cannot express (mirrors UpdateOpportunityData).
 *
 * `opportunityId` and `code` are deliberately ABSENT: `opportunity_id` is
 * `prohibited` (AC-025, immutable) and `code` is not even a rule at this
 * layer (immutable, read-only ceiling once the model exists) — neither ever
 * reaches this DTO.
 *
 * `offerLines`/`costLines` follow the full-replace convention (D-8): null
 * means "not submitted, leave the existing set untouched"; an array
 * (including empty) authoritatively replaces it.
 *
 * `attributeValues` (spec 0084, D-1/D-5) follows the SAME sparse convention
 * as `UpdateOpportunityData`'s former field: `null` when the key was absent
 * — and sparse WITHIN itself too, a code the map leaves out keeps its
 * persisted value (QuoteAttributeValueWriter owns that merge).
 *
 * `workflowStatusId` is the OPTIONAL explicit `quote_workflow_status_id`
 * override (AC-021/022): submitted-and-non-null is validated
 * (ValidatesQuoteWorkflowStatus) to belong to the resolved set and written
 * verbatim (note-gated by QuoteWorkflowStatusWriter, AC-023/024/025); NOT
 * submitted, or submitted null, both mean "let QuoteWorkflowResolver decide"
 * — it is NEVER part of submittedAttributes() (never mass-assigned, always
 * written by the resolver/writer). `note` (AC-023) accompanies an override
 * whose destination `requires_note`.
 *
 * `rewards` (spec 0059 D-3, extended to the Offerta origin) follows the same
 * null-means-untouched convention as the line sets: absent leaves the
 * persisted assignments alone, `[]` clears them all. Never in
 * submittedAttributes() — RewardAssignmentWriter owns its own table.
 */
final readonly class UpdateQuoteData
{
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    public function __construct(
        public ?string $title = null,
        public bool $titleSubmitted = false,
        public ?int $workflowStatusId = null,
        public bool $workflowStatusIdSubmitted = false,
        public ?string $note = null,
        public ?int $commercialId = null,
        public bool $commercialIdSubmitted = false,
        public ?int $reporterId = null,
        public bool $reporterIdSubmitted = false,
        public ?int $supervisorId = null,
        public bool $supervisorIdSubmitted = false,
        public ?string $internalNotes = null,
        public bool $internalNotesSubmitted = false,
        public ?array $offerLines = null,
        public ?array $costLines = null,
        // Appended after the pre-existing parameters (user directive
        // 2026-07-30) so every positional/partial construction of this DTO
        // keeps working unchanged.
        public ?int $companyId = null,
        public bool $companyIdSubmitted = false,
        public ?int $companySiteId = null,
        public bool $companySiteIdSubmitted = false,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
        // Appended after the pre-existing parameters (spec 0070, D-3/D-8):
        // NOT resolved against the Opportunity — a plain submitted/persisted
        // toggle, exactly like every other optional FK here.
        public ?int $layoutId = null,
        public bool $layoutIdSubmitted = false,
        // Appended after the pre-existing parameters (user directive
        // 2026-07-30): a plain submitted/persisted toggle, like every other
        // optional FK here.
        public ?int $paymentMethodId = null,
        public bool $paymentMethodIdSubmitted = false,
        // Appended after the pre-existing parameters (spec 0084), same
        // positional-compat reason as every other appended field here.
        /** @var array<string, mixed>|null */
        public ?array $attributeValues = null,
        // Appended after the pre-existing parameters (spec 0059 D-3, extended
        // to the Offerta origin), same positional-compat reason as above.
        /** @var array<int, int>|null */
        public ?array $rewards = null,
    ) {}

    /**
     * Build from the validated UpdateQuoteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: array_key_exists('title', $data) ? (string) $data['title'] : null,
            titleSubmitted: array_key_exists('title', $data),
            workflowStatusId: self::nullableInt($data, 'quote_workflow_status_id'),
            workflowStatusIdSubmitted: array_key_exists('quote_workflow_status_id', $data),
            note: array_key_exists('note', $data) ? $data['note'] : null,
            commercialId: self::nullableInt($data, 'commercial_id'),
            commercialIdSubmitted: array_key_exists('commercial_id', $data),
            reporterId: self::nullableInt($data, 'reporter_id'),
            reporterIdSubmitted: array_key_exists('reporter_id', $data),
            supervisorId: self::nullableInt($data, 'supervisor_id'),
            supervisorIdSubmitted: array_key_exists('supervisor_id', $data),
            internalNotes: array_key_exists('internal_notes', $data) ? $data['internal_notes'] : null,
            internalNotesSubmitted: array_key_exists('internal_notes', $data),
            offerLines: array_key_exists('offer_lines', $data) ? self::normalizeLines($data['offer_lines']) : null,
            costLines: array_key_exists('cost_lines', $data) ? self::normalizeLines($data['cost_lines']) : null,
            companyId: self::nullableInt($data, 'company_id'),
            companyIdSubmitted: array_key_exists('company_id', $data),
            companySiteId: self::nullableInt($data, 'company_site_id'),
            companySiteIdSubmitted: array_key_exists('company_site_id', $data),
            operationalSiteId: self::nullableInt($data, 'operational_site_id'),
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
            layoutId: self::nullableInt($data, 'layout_id'),
            layoutIdSubmitted: array_key_exists('layout_id', $data),
            paymentMethodId: self::nullableInt($data, 'payment_method_id'),
            paymentMethodIdSubmitted: array_key_exists('payment_method_id', $data),
            attributeValues: array_key_exists('attribute_values', $data)
                ? (array) $data['attribute_values']
                : null,
            rewards: array_key_exists('rewards', $data) ? self::normalizeRewardTypeIds($data['rewards']) : null,
        );
    }

    /**
     * @return array<int, QuoteLineData>
     */
    private static function normalizeLines(mixed $rows): array
    {
        return array_map(
            static fn (array $row): QuoteLineData => QuoteLineData::fromValidated($row),
            (array) $rows,
        );
    }

    /**
     * The submitted `{reward_type_id}` rows flattened to the plain, deduped
     * id list RewardAssignmentWriter::sync() consumes.
     *
     * @return array<int, int>
     */
    private static function normalizeRewardTypeIds(mixed $rows): array
    {
        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['reward_type_id'],
            (array) $rows,
        )));
    }

    public function hasRewards(): bool
    {
        return $this->rewards !== null;
    }

    public function hasOfferLines(): bool
    {
        return $this->offerLines !== null;
    }

    public function hasCostLines(): bool
    {
        return $this->costLines !== null;
    }

    /**
     * Only the scalar attributes the client actually submitted, ready for a
     * partial mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->titleSubmitted) {
            $attributes['title'] = $this->title;
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

        if ($this->companyIdSubmitted) {
            $attributes['company_id'] = $this->companyId;
        }

        if ($this->companySiteIdSubmitted) {
            $attributes['company_site_id'] = $this->companySiteId;
        }

        if ($this->operationalSiteIdSubmitted) {
            $attributes['operational_site_id'] = $this->operationalSiteId;
        }

        if ($this->layoutIdSubmitted) {
            $attributes['layout_id'] = $this->layoutId;
        }

        if ($this->paymentMethodIdSubmitted) {
            $attributes['payment_method_id'] = $this->paymentMethodId;
        }

        if ($this->internalNotesSubmitted) {
            $attributes['internal_notes'] = $this->internalNotes;
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
