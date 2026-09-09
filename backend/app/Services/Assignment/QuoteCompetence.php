<?php

namespace App\Services\Assignment;

use App\Models\Quote;

/**
 * The competence requirement of a Gestione richieste offer (spec 0110,
 * INV-1). Unlike leads and staged rows, an offer needs no fallback: its
 * opportunity already carries explicit `opportunity_product_lines`
 * (business function + product category pairs, spec 0040 rev.3), and the
 * category half of those pairs IS the requirement — the function half is
 * re-derived from the category by OperatorCompetence, so the two readings
 * cannot disagree.
 */
final class QuoteCompetence
{
    /**
     * quote id => required category ids, in one query.
     *
     * @param  array<int, int>  $quoteIds
     * @return array<int, array<int, int>>
     */
    public function requiredByQuote(array $quoteIds): array
    {
        if ($quoteIds === []) {
            return [];
        }

        $quotes = Quote::query()
            ->with('opportunity.productLines:id,opportunity_id,product_category_id')
            ->whereIn('id', $quoteIds)
            ->get(['id', 'opportunity_id']);

        $required = [];

        foreach ($quotes as $quote) {
            $required[(int) $quote->id] = $quote->opportunity?->productLines
                ->pluck('product_category_id')
                ->filter()
                ->map(intval(...))
                ->unique()
                ->values()
                ->all() ?? [];
        }

        return $required;
    }

    /**
     * The UNION of the offers' requirements (spec 0110 D-14).
     *
     * @param  array<int, int>  $quoteIds
     * @return array<int, int>
     */
    public function requiredUnion(array $quoteIds): array
    {
        $union = array_merge(...array_values($this->requiredByQuote($quoteIds)) ?: [[]]);

        sort($union);

        return array_values(array_unique($union));
    }
}
