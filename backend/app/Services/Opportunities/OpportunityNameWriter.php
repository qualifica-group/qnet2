<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Services\Quotes\RevenueProductTitleBuilder;

/**
 * Single owner of `opportunities.name` writes (spec 0171). The automatic title
 * comes from RevenueProductTitleBuilder (`OPP_{id} - <quoted products>`); a
 * title typed by the user is kept as-is and flagged `name_is_manual`, so the
 * re-derivation that follows every quote write no longer overwrites it.
 */
final class OpportunityNameWriter
{
    public function __construct(private readonly RevenueProductTitleBuilder $titleBuilder) {}

    /**
     * Applies a client-submitted title (D-3): null (blank, after the
     * ConvertEmptyStringsToNull middleware) or equal to the automatic title
     * keeps the opportunity automatic; any other value becomes manual.
     */
    public function write(Opportunity $opportunity, ?string $submittedName): void
    {
        $derivedName = $this->titleBuilder->forOpportunity($opportunity);
        $isManual = $submittedName !== null && $submittedName !== $derivedName;

        $opportunity->forceFill([
            'name' => $isManual ? $submittedName : $derivedName,
            'name_is_manual' => $isManual,
        ])->save();
    }

    /**
     * Re-derives the automatic title from a fresh read (spec 0077); a manual
     * title is left untouched (D-4).
     */
    public function recalculate(int $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);

        if ($opportunity->name_is_manual) {
            return;
        }

        $opportunity->forceFill(['name' => $this->titleBuilder->forOpportunity($opportunity)])->save();
    }
}
