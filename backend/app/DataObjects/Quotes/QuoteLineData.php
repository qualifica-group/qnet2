<?php

declare(strict_types=1);

namespace App\DataObjects\Quotes;

/**
 * One row of a quote's `offer_lines`/`cost_lines` payload (spec 0065, D-11):
 * the SAME shape on both tabs, discriminated by which array it came from —
 * QuoteService is the one that stamps `line_type` when it writes the row.
 * ONLY `product_id` is stored (D-7): the amounts below are RAW inputs,
 * rounded and frozen by QuoteTotalsCalculator/QuoteLineWriter, never by this
 * DTO. `sortOrder` null means "use this row's own index in the submitted
 * array" (AC-038). `additional_description` is optional per channel: only a
 * row that carries the key changes it, so a channel that never edits it (the
 * Gestione Richieste grid cell, request creation) cannot wipe it on its
 * full-replace resubmit.
 *
 * `offerLineId`/`offerLineIndex` (spec 0144, D-4): the two mutually exclusive
 * ways a COST row references a REVENUE row of the same quote — an already
 * persisted one by id, or one submitted alongside it in the SAME request by
 * its 0-based position in `offer_lines`. Both null = generic cost. `prohibited`
 * on every other channel (App\Quotes\QuoteLineRules), so they only ever carry
 * a value here when the row came from `cost_lines`.
 */
final readonly class QuoteLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public float $unitPrice,
        public ?int $vatRateId,
        public ?int $sortOrder,
        public ?int $id = null,
        /** @var array<int, QuoteLineCommissionData>|null */
        public ?array $commissions = null,
        public ?string $additionalDescription = null,
        /** `false` = the key was absent: the writer keeps the stored value (see QuoteLineWriter::sync()). */
        public bool $hasAdditionalDescription = false,
        public ?int $offerLineId = null,
        public ?int $offerLineIndex = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromValidated(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            productId: (int) $row['product_id'],
            quantity: (float) $row['quantity'],
            unitPrice: (float) $row['unit_price'],
            vatRateId: isset($row['vat_rate_id']) ? (int) $row['vat_rate_id'] : null,
            sortOrder: isset($row['sort_order']) ? (int) $row['sort_order'] : null,
            commissions: array_key_exists('commissions', $row)
                ? array_map(
                    static fn (array $commission): QuoteLineCommissionData => QuoteLineCommissionData::fromValidated($commission),
                    (array) $row['commissions'],
                )
                : null,
            additionalDescription: isset($row['additional_description']) ? (string) $row['additional_description'] : null,
            hasAdditionalDescription: array_key_exists('additional_description', $row),
            offerLineId: isset($row['offer_line_id']) ? (int) $row['offer_line_id'] : null,
            offerLineIndex: isset($row['offer_line_index']) ? (int) $row['offer_line_index'] : null,
        );
    }
}
