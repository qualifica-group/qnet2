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
 * array" (AC-038).
 */
final readonly class QuoteLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public float $unitPrice,
        public ?int $vatRateId,
        public ?int $sortOrder,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromValidated(array $row): self
    {
        return new self(
            productId: (int) $row['product_id'],
            quantity: (float) $row['quantity'],
            unitPrice: (float) $row['unit_price'],
            vatRateId: isset($row['vat_rate_id']) ? (int) $row['vat_rate_id'] : null,
            sortOrder: isset($row['sort_order']) ? (int) $row['sort_order'] : null,
        );
    }
}
