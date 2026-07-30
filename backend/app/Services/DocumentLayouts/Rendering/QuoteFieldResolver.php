<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resolves the `quote`, `totals` and `document` variable categories (spec
 * 0069's frozen catalogue) — the three categories with no PII and no
 * relation traversal beyond the Quote itself, split out of VariableResolver
 * to keep it under the file-size soft limit (engineering.md §6).
 *
 * `totals.*_gross` is DERIVED (net + vat), never a column — spec 0069's
 * data_contract is explicit that this must never be read off a persisted
 * field.
 */
final class QuoteFieldResolver
{
    /**
     * @var array<string, string>
     */
    private const array TOTALS_NET_VAT_PAIRS = [
        'revenue_gross' => 'revenue',
        'cost_gross' => 'cost',
    ];

    public function quote(string $key, Quote $quote): ?string
    {
        return match ($key) {
            'code' => ValueFormatter::text($quote->code),
            'title' => ValueFormatter::text($quote->title),
            'internal_notes' => ValueFormatter::text($quote->internal_notes),
            'status_name' => ValueFormatter::text($quote->quoteStatus?->name),
            'created_at' => ValueFormatter::date($quote->created_at),
            'updated_at' => ValueFormatter::date($quote->updated_at),
            default => null,
        };
    }

    public function totals(string $key, Quote $quote): ?string
    {
        if (isset(self::TOTALS_NET_VAT_PAIRS[$key])) {
            $prefix = self::TOTALS_NET_VAT_PAIRS[$key];
            $net = (float) $quote->{"{$prefix}_net"};
            $vat = (float) $quote->{"{$prefix}_vat"};

            return ValueFormatter::currency($net + $vat);
        }

        return match ($key) {
            'revenue_net', 'revenue_vat', 'cost_net', 'cost_vat', 'margin_net' => ValueFormatter::currency((float) $quote->{$key}),
            default => null,
        };
    }

    public function document(string $key, User $actor): ?string
    {
        return match ($key) {
            'generated_at' => ValueFormatter::date(Carbon::now()),
            'generated_by' => ValueFormatter::text($actor->name),
            default => null,
        };
    }
}
