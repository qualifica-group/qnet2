<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteLineType;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The automatic title of an Opportunity and of an Offerta (spec 0171, D-7):
 * the record's code, then " - " and the distinct names of the products on its
 * REVENUE lines (COST lines never contribute), joined by " + " within the
 * 191-char column. With no revenue line the title is the code alone.
 *
 * Opportunity: code `OPP_{id}`, products of ALL its quotes (spec 0077 order:
 * quote created_at/id, then line sort_order/id). Offerta: its own `code`,
 * products of its own lines. Products of interest never contribute (D-2).
 */
final class RevenueProductTitleBuilder
{
    private const int MAX_LENGTH = 191;

    private const string OPPORTUNITY_CODE_PREFIX = 'OPP_';

    private const string CODE_SEPARATOR = ' - ';

    private const string PRODUCT_SEPARATOR = ' + ';

    private const string TRUNCATION_SUFFIX = ' …';

    public function forOpportunity(Opportunity $opportunity): string
    {
        $names = $this->revenueProductNames(
            fn (Builder $query) => $query->where('quotes.opportunity_id', $opportunity->id),
        );

        return $this->compose($this->opportunityCode($opportunity->id), $names);
    }

    public function forQuote(Quote $quote): string
    {
        return $this->compose($quote->code, $this->quoteRevenueProductNames($quote));
    }

    /**
     * The code an Opportunity's title starts with.
     */
    public function opportunityCode(int $opportunityId): string
    {
        return self::OPPORTUNITY_CODE_PREFIX.$opportunityId;
    }

    /**
     * The product names forQuote() titles $quote with.
     *
     * @return array<int, string>
     */
    public function quoteRevenueProductNames(Quote $quote): array
    {
        return $this->revenueProductNames(
            fn (Builder $query) => $query->where('quote_lines.quote_id', $quote->id),
        );
    }

    /**
     * One query, no lazy loading: revenue lines joined to their quote (for
     * the ordering columns) and to the product (for the display name),
     * deduplicated by product (first occurrence wins).
     *
     * @param  callable(Builder<QuoteLine>): Builder<QuoteLine>  $scope
     * @return array<int, string>
     */
    private function revenueProductNames(callable $scope): array
    {
        $query = QuoteLine::query()
            ->join('quotes', 'quotes.id', '=', 'quote_lines.quote_id')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.line_type', QuoteLineType::Revenue)
            ->orderBy('quotes.created_at')
            ->orderBy('quotes.id')
            ->orderBy('quote_lines.sort_order')
            ->orderBy('quote_lines.id');

        /** @var Collection<int, QuoteLine> $rows */
        $rows = $scope($query)->get(['quote_lines.product_id', 'products.name as product_name']);

        return $rows->unique('product_id')->pluck('product_name')->values()->all();
    }

    /**
     * The title a record coded $code carries with $names on its REVENUE lines:
     * public for the bulk sample seeder, whose rows copy a template's lines and
     * so are titled from its names without a query per row.
     *
     * @param  array<int, string>  $names
     */
    public function compose(string $code, array $names): string
    {
        if ($names === []) {
            return $code;
        }

        $prefix = $code.self::CODE_SEPARATOR;

        return $prefix.$this->joinWithinBudget($names, self::MAX_LENGTH - mb_strlen($prefix));
    }

    /**
     * Joins $names within $budget characters; past it, keeps only the whole
     * names that fit and appends the truncation suffix (a name is never split).
     *
     * @param  array<int, string>  $names
     */
    private function joinWithinBudget(array $names, int $budget): string
    {
        $full = implode(self::PRODUCT_SEPARATOR, $names);

        if (mb_strlen($full) <= $budget) {
            return $full;
        }

        $budget -= mb_strlen(self::TRUNCATION_SUFFIX);
        $result = '';

        foreach ($names as $name) {
            $candidate = $result === '' ? $name : $result.self::PRODUCT_SEPARATOR.$name;

            if (mb_strlen($candidate) > $budget) {
                break;
            }

            $result = $candidate;
        }

        return $result.self::TRUNCATION_SUFFIX;
    }
}
