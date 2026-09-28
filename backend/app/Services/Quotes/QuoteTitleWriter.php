<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;

/**
 * Single owner of `quotes.title` writes (spec 0171 rev.2, D-8), the Offerta
 * twin of OpportunityNameWriter: the automatic title (`<code> - <products>`)
 * is kept in sync with the offer's own revenue lines until the user types
 * their own, flagged `title_is_manual`.
 */
final class QuoteTitleWriter
{
    public function __construct(private readonly RevenueProductTitleBuilder $titleBuilder) {}

    /**
     * Applies a client-submitted title: null (blank) or equal to the
     * automatic title keeps the offer automatic; any other value is manual.
     * Called after the offer lines are written, so the comparison sees them.
     */
    public function write(Quote $quote, ?string $submittedTitle): void
    {
        $derivedTitle = $this->titleBuilder->forQuote($quote);
        $isManual = $submittedTitle !== null && $submittedTitle !== $derivedTitle;

        $quote->forceFill([
            'title' => $isManual ? $submittedTitle : $derivedTitle,
            'title_is_manual' => $isManual,
        ])->save();
    }

    /** Re-derives the automatic title; a manual title is left untouched. */
    public function recalculate(Quote $quote): void
    {
        if ($quote->title_is_manual) {
            return;
        }

        $quote->forceFill(['title' => $this->titleBuilder->forQuote($quote)])->save();
    }
}
