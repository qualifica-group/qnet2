<?php

declare(strict_types=1);

namespace App\Services\ProductLines;

use Illuminate\Database\Eloquent\Model;

/**
 * The single write path for a record's `product_lines` collection (funzione
 * aziendale + categoria prodotto, spec 0040 amendment rev.3), shared by
 * every owner that carries one — Opportunity (plus its request-management
 * channel, which writes the SAME Opportunity rows), Project and Campaign
 * (spec 0094). One writer, so the delete-all + insert rule cannot diverge
 * between owners or between an owner's own write channels — the same
 * reason OpportunityProductInterestWriter exists for the sibling collection.
 *
 * $owner is untyped beyond Model on purpose: the collection is reached via
 * whichever concrete `productLines()` HasMany relation the owner exposes,
 * never re-declared here.
 */
final class ProductLineWriter
{
    /**
     * Full-replace sync: delete-all + insert, idempotent within the caller's
     * transaction. Every row is expected to be already valid (no duplicate
     * pair, category belonging to the paired business function) —
     * ValidatesProductLines is what enforces that, on each write endpoint.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $lines
     */
    public function sync(Model $owner, array $lines): void
    {
        $owner->productLines()->delete();

        foreach ($lines as $line) {
            $owner->productLines()->create($line);
        }

        // The relation is a resolution criterion for the workflow and for the
        // applicable attributes: a stale loaded copy would make both read the
        // pre-sync rows.
        $owner->unsetRelation('productLines');
    }
}
