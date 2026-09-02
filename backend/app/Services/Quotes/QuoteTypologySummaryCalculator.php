<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteLineType;
use App\Models\ProductTypology;
use App\Models\Quote;

/**
 * The "Riepilogo per Tipologia Prodotto" aggregate (spec 0099, D-6/D-7): the
 * Offer's REVENUE lines grouped by the typology of their product, one entry
 * per CONFIGURED typology.
 *
 * No new economic formula (requirement 6): it sums each line's own persisted,
 * frozen `net_amount` — the very column QuoteTotalsCalculator::aggregates()
 * sums into `revenue_net` — so quantity, price, discounts and manual edits are
 * already baked in and the buckets always reconcile with the Offer's own
 * imponibile. Mirrors QuoteCommissionSummaryCalculator's shape and its
 * revenue-only scope.
 *
 * The typology is reached LIVE through the product (D-5): `quote_lines` holds
 * no typology column, so this is a join, not a snapshot read.
 *
 * D-7: the result lists EVERY row of `product_typologies`, ordered by name,
 * zero-filled where the offer has no matching line — and it is driven purely
 * by that table, so a typology added later appears with no code change. No
 * typology name or code is referenced anywhere in this class.
 */
final class QuoteTypologySummaryCalculator
{
    /**
     * @return array<int, array{id: int, name: string, net: string}>
     */
    public function totals(Quote $quote): array
    {
        $netByTypologyId = $this->netByTypologyId($quote);

        return ProductTypology::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (ProductTypology $typology): array => [
                'id' => $typology->id,
                'name' => $typology->name,
                'net' => $this->format($netByTypologyId[$typology->id] ?? 0),
            ])
            ->all();
    }

    /**
     * The offer's revenue net grouped by `products.product_typology_id`.
     *
     * @return array<int, mixed>
     */
    private function netByTypologyId(Quote $quote): array
    {
        /** @var array<array-key, mixed> $totals */
        $totals = $quote->lines()
            // Quote::lines() carries orderBy('sort_order') for the read path;
            // inherited here it lands in an aggregate query and MySQL rejects
            // it under only_full_group_by (sort_order is neither grouped nor
            // aggregated). Row order is meaningless for a GROUP BY total —
            // same reasoning as QuoteCommissionSummaryCalculator.
            ->reorder()
            ->where('quote_lines.line_type', QuoteLineType::Revenue)
            ->join('products', 'quote_lines.product_id', '=', 'products.id')
            ->selectRaw('products.product_typology_id, SUM(quote_lines.net_amount) as total')
            ->groupBy('products.product_typology_id')
            // Unqualified on purpose: pluck() keys off the RESULT ROW's
            // property, which the driver names after the selected column's
            // last segment, not after the qualified expression.
            ->pluck('total', 'product_typology_id')
            ->all();

        // The FK arrives as an int on some drivers and a numeric string on
        // others; the caller looks it up by the model's int id.
        $byId = [];

        foreach ($totals as $typologyId => $total) {
            $byId[(int) $typologyId] = $total;
        }

        return $byId;
    }

    private function format(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
