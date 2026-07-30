<?php

declare(strict_types=1);

namespace App\DataObjects\Quotes;

/**
 * Validated payload for a partial (PATCH) quote update
 * (PUT/PATCH /api/quotes/{quote}, spec 0065). Every scalar is a legitimately
 * nullable VALUE, so the `*Submitted` flags carry the "was this key actually
 * present" distinction a plain property cannot express (mirrors
 * UpdateOpportunityData).
 *
 * `opportunityId` and `code` are deliberately ABSENT: `opportunity_id` is
 * `prohibited` (AC-025, immutable) and `code` is not even a rule at this
 * layer (immutable, read-only ceiling once the model exists) — neither ever
 * reaches this DTO.
 *
 * `offerLines`/`costLines` follow the full-replace convention (D-8): null
 * means "not submitted, leave the existing set untouched"; an array
 * (including empty) authoritatively replaces it.
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
        public ?int $quoteStatusId = null,
        public bool $quoteStatusIdSubmitted = false,
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
            quoteStatusId: self::nullableInt($data, 'quote_status_id'),
            quoteStatusIdSubmitted: array_key_exists('quote_status_id', $data),
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

        if ($this->quoteStatusIdSubmitted) {
            $attributes['quote_status_id'] = $this->quoteStatusId;
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
