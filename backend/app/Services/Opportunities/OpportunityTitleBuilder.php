<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\QuoteLineType;
use App\Models\Opportunity;
use App\Models\QuoteLine;
use Illuminate\Support\Collection;

/**
 * Derives `opportunities.name` from the products of interest actually
 * quoted (spec 0077, D-3/D-4): the concatenated names of every distinct
 * product referenced by a REVENUE line across ALL the opportunity's quotes,
 * in deterministic order, deduplicated (first occurrence wins), COST lines
 * never contributing. Falls back to the pre-existing `OPP_{id}` scheme
 * (spec 0057, D-5) when the opportunity has no revenue line at all.
 *
 * Called imperatively by QuoteService inside its own transaction, right
 * after `persistAggregates()` — the repo has no model observers/events
 * (constraint, spec 0077).
 */
final class OpportunityTitleBuilder
{
    private const int MAX_LENGTH = 191;

    private const string SEPARATOR = ' + ';

    private const string TRUNCATION_SUFFIX = ' …';

    private const string FALLBACK_PREFIX = 'OPP_';

    public function build(Opportunity $opportunity): string
    {
        // Step 1: the deduplicated, ordered product names of every REVENUE
        // line across all of the opportunity's quotes (D-4).
        $names = $this->collectRevenueProductNames($opportunity->id);

        // Step 2: no revenue line at all -> the unchanged D-5 fallback.
        if ($names === []) {
            return self::FALLBACK_PREFIX.$opportunity->id;
        }

        // Step 3: join within the 191-char `opportunities.name` limit.
        return $this->joinWithinLimit($names);
    }

    /**
     * One query, no lazy loading: joins `quote_lines` to its own `quotes`
     * (for the FK filter and the `created_at`/`id` ordering columns) and to
     * `products` (for the display name), ordered exactly per the spec's
     * `derivazione_nome` (quotes.created_at, quotes.id, quote_lines.sort_order,
     * quote_lines.id).
     *
     * @return array<int, string>
     */
    private function collectRevenueProductNames(int $opportunityId): array
    {
        $rows = QuoteLine::query()
            ->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quotes.opportunity_id', $opportunityId)
            ->where('quote_lines.line_type', QuoteLineType::Revenue)
            ->orderBy('quotes.created_at')
            ->orderBy('quotes.id')
            ->orderBy('quote_lines.sort_order')
            ->orderBy('quote_lines.id')
            ->get(['quote_lines.product_id', 'products.name as product_name']);

        return $this->dedupeByProductId($rows);
    }

    /**
     * @param  Collection<int, QuoteLine>  $rows
     * @return array<int, string>
     */
    private function dedupeByProductId(Collection $rows): array
    {
        $seenProductIds = [];
        $names = [];

        foreach ($rows as $row) {
            if (in_array($row->product_id, $seenProductIds, true)) {
                continue;
            }

            $seenProductIds[] = $row->product_id;
            $names[] = $row->product_name;
        }

        return $names;
    }

    /**
     * Concatenates $names with SEPARATOR as long as the result stays within
     * MAX_LENGTH; falls back to a budgeted truncation the moment a name
     * would push it over (AC-035: never split a name in half).
     *
     * @param  array<int, string>  $names
     */
    private function joinWithinLimit(array $names): string
    {
        $full = implode(self::SEPARATOR, $names);

        if (mb_strlen($full) <= self::MAX_LENGTH) {
            return $full;
        }

        return $this->truncatedJoin($names);
    }

    /**
     * @param  array<int, string>  $names
     */
    private function truncatedJoin(array $names): string
    {
        $budget = self::MAX_LENGTH - mb_strlen(self::TRUNCATION_SUFFIX);
        $result = '';

        foreach ($names as $name) {
            $candidate = $result === '' ? $name : $result.self::SEPARATOR.$name;

            if (mb_strlen($candidate) > $budget) {
                break;
            }

            $result = $candidate;
        }

        return $result.self::TRUNCATION_SUFFIX;
    }
}
