<?php

declare(strict_types=1);

namespace App\DataObjects\Quotes;

/**
 * Validated payload for creating a quote (POST /api/quotes, spec 0065).
 * `code` is a manual override (D-13, same pattern as
 * CreateProjectData/CreateProductData): null means "let QuoteService generate
 * QUO-0001..."; it is deliberately absent from attributes() since it is
 * never in Quote's #[Fillable] — the service assigns it directly.
 *
 * `commercialId`/`reporterId`/`supervisorId` are a SNAPSHOT (D-3): the
 * `*Submitted` flag distinguishes "the client omitted this field entirely"
 * (QuoteService inherits the Opportunity's current value, AC-020) from "the
 * client explicitly submitted a value, even null" (that exact value wins,
 * AC-021) — a plain nullable property cannot express that difference.
 * `operationalSiteId` (user directive 2026-07-30) joins that snapshot set and
 * carries the same flag; `companyId`/`companySiteId` do not — they have no
 * Opportunity counterpart to inherit from, so a plain nullable value is
 * enough.
 *
 * `offerLines`/`costLines` follow the CreateOpportunityData::$productLines
 * convention: null means "no rows submitted for this tab", an array
 * (including empty) is an authoritative full-replace set (D-8).
 */
final readonly class CreateQuoteData
{
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    public function __construct(
        public ?string $code,
        public string $title,
        public int $opportunityId,
        public ?int $quoteStatusId,
        public ?int $commercialId,
        public bool $commercialIdSubmitted,
        public ?int $reporterId,
        public bool $reporterIdSubmitted,
        public ?int $supervisorId,
        public bool $supervisorIdSubmitted,
        public ?string $internalNotes,
        public ?array $offerLines = null,
        public ?array $costLines = null,
        // Appended after the pre-existing parameters (user directive
        // 2026-07-30) so every positional/partial construction of this DTO
        // keeps working unchanged.
        public ?int $companyId = null,
        public ?int $companySiteId = null,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
    ) {}

    /**
     * Build from the validated StoreQuoteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: self::nullIfEmpty($data['code'] ?? null),
            title: (string) $data['title'],
            opportunityId: (int) $data['opportunity_id'],
            quoteStatusId: isset($data['quote_status_id']) ? (int) $data['quote_status_id'] : null,
            commercialId: isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            commercialIdSubmitted: array_key_exists('commercial_id', $data),
            reporterId: isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            reporterIdSubmitted: array_key_exists('reporter_id', $data),
            supervisorId: isset($data['supervisor_id']) ? (int) $data['supervisor_id'] : null,
            supervisorIdSubmitted: array_key_exists('supervisor_id', $data),
            internalNotes: $data['internal_notes'] ?? null,
            offerLines: array_key_exists('offer_lines', $data) ? self::normalizeLines($data['offer_lines']) : null,
            costLines: array_key_exists('cost_lines', $data) ? self::normalizeLines($data['cost_lines']) : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            companySiteId: isset($data['company_site_id']) ? (int) $data['company_site_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
        );
    }

    /**
     * An empty-string `code` means "no manual code" just as much as an
     * absent one — both fall back to the sequential generator.
     */
    private static function nullIfEmpty(mixed $value): ?string
    {
        return $value === '' ? null : $value;
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
     * The quote's own scalar attributes for a mass-assignment create
     * (framework array boundary). `code` is NOT included: QuoteService
     * assigns it directly, after mass-assignment (D-13).
     * `commercial_id`/`reporter_id`/`supervisor_id` carry the RAW submitted
     * values here — QuoteService overrides them with the Opportunity's
     * snapshot for every one of the 3 that was NOT submitted (D-3).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'opportunity_id' => $this->opportunityId,
            'quote_status_id' => $this->quoteStatusId,
            'commercial_id' => $this->commercialId,
            'reporter_id' => $this->reporterId,
            'supervisor_id' => $this->supervisorId,
            'company_id' => $this->companyId,
            'company_site_id' => $this->companySiteId,
            'operational_site_id' => $this->operationalSiteId,
            'internal_notes' => $this->internalNotes,
        ];
    }
}
