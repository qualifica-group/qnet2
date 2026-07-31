<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Models\Opportunity;

/**
 * The single write path for an opportunity's `product_lines` collection
 * (funzione aziendale + categoria prodotto, spec 0040 amendment rev.3),
 * shared by BOTH channels that replace it: the opportunities CRUD
 * (OpportunityService) and the request-management work panel
 * (RequestManagementService). One writer, so the delete-all + insert rule
 * cannot diverge between them — the same reason
 * OpportunityProductInterestWriter exists for the sibling collection.
 */
final class OpportunityProductLineWriter
{
    /**
     * Full-replace sync: delete-all + insert, idempotent within the caller's
     * transaction. Every row is expected to be already valid (no duplicate
     * pair, category belonging to the paired business function) —
     * ValidatesProductLines is what enforces that, on each write endpoint.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $lines
     */
    public function sync(Opportunity $opportunity, array $lines): void
    {
        $opportunity->productLines()->delete();

        foreach ($lines as $line) {
            $opportunity->productLines()->create($line);
        }

        // The relation is a resolution criterion for the workflow and for the
        // applicable attributes: a stale loaded copy would make both read the
        // pre-sync rows.
        $opportunity->unsetRelation('productLines');
    }
}
