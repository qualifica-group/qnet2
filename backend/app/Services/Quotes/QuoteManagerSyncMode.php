<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\CategoryManagementMode;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Opportunities\OpportunityQuoteLimit;

/**
 * THE RULE (spec 0087, D-7): an Opportunita' is "Gestori Account
 * sincronizzati" when its product category branch caps it at a single
 * Offerta (`OpportunityQuoteLimit::isSingleQuoteBranch()`) AND manages it on
 * a single product-category line (`OpportunityProductLineCoverage::
 * managementModeOf() === CategoryManagementMode::Single`). Both settings are
 * independently owned (D-7 context) and, verified, no other point in the
 * codebase combines them — this is the ONE place the AND is built.
 *
 * In this mode `App\Services\Quotes\QuoteManagerWriter` replaces (never
 * appends to) the counterpart's GA list on every write, from either side
 * (bidirectional, INV-4): the two lists are identical by construction, so
 * the D-6 appartenenza rule this class's callers otherwise enforce becomes
 * inert here.
 */
final class QuoteManagerSyncMode
{
    public function __construct(
        private readonly OpportunityQuoteLimit $quoteLimit,
        private readonly OpportunityProductLineCoverage $coverage,
    ) {}

    public function isSynchronized(Opportunity $opportunity): bool
    {
        return $this->quoteLimit->isSingleQuoteBranch($opportunity)
            && $this->coverage->managementModeOf($opportunity) === CategoryManagementMode::Single;
    }
}
