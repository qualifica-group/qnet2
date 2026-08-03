<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityProductLineWriter;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Validation\ValidationException;

/**
 * "Funzione aziendale" + "categoria prodotto" as edited from the work panel
 * (user directive 2026-07-31: the commercials change them on an existing
 * request, not only while creating it — the same `product_lines` collection
 * the create form already writes).
 *
 * The replace itself is delegated to the shared OpportunityProductLineWriter,
 * so this channel and the opportunities CRUD sync the collection identically.
 * What lives HERE is what only this channel needs: the diff — nothing is
 * rewritten, nor logged, when the pairs are unchanged.
 *
 * Spec 0075, D-2: it is also where the set's OWN rules are enforced
 * (ProductLineSetValidator), because this writer is reached by BOTH write
 * channels — the work panel, whose FormRequest has already applied the very
 * same rules, and the inline cell editor, which has no FormRequest at all.
 * Only a genuine change is validated: resubmitting the persisted pairs
 * unchanged can never fail, the same semantics as the spec 0074 D-3b
 * exemption it passes along.
 *
 * The coherence of the resulting classification with the request's products
 * of interest is NOT checked here: it depends on the whole payload (the
 * products may be replaced in the same PATCH), so it belongs to the caller,
 * which owns both — see RequestProductCategoryCoherence.
 */
final class RequestProductLineWriter
{
    /**
     * The payload key an inline cell edit's 422 lands on: the panel names its
     * own per-row paths, a cell edit has a single value to blame.
     */
    private const string ERROR_FIELD = 'product_lines';

    public function __construct(
        private readonly OpportunityProductLineWriter $productLineWriter,
        private readonly ProductLineSetValidator $setValidator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $submitted  the validated `product_lines` rows
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     * @return bool whether the collection actually changed — a workflow
     *              resolution criterion the caller re-runs on (spec 0047)
     *
     * @throws ValidationException the new set breaks a product-line rule
     */
    public function apply(Opportunity $opportunity, array $submitted, array &$changed, array &$old): bool
    {
        // Step 1: both sides in the same shape, compared as an unordered SET
        // of pairs (a line's position carries no meaning).
        $current = $this->currentPairs($opportunity);
        $next = $this->normalize($submitted);

        if ($this->pairKeys($current) === $this->pairKeys($next)) {
            return false;
        }

        // Step 1-bis (spec 0075, D-2): the set's own rules, on the channel
        // that has no FormRequest as much as on the one that has. The
        // categories already persisted stay exempt from the selectability
        // rule (spec 0074, D-3b) — the actor is not forced to fix a
        // classification they did not create.
        // Validated on the SUBMITTED rows, not on the normalized ones: an
        // absent id must be reported as missing, not as a non-existent `0`.
        $this->setValidator->assert(
            array_values($submitted),
            array_column($current, 'product_category_id'),
            self::ERROR_FIELD,
        );

        // Step 2: replace, and report the change for the caller's audit entry
        // (the collection is a relation, so it never reaches the automatic
        // fillable-diff log).
        $this->productLineWriter->sync($opportunity, $next);

        $old['product_lines'] = $current;
        $changed['product_lines'] = $next;

        return true;
    }

    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function currentPairs(Opportunity $opportunity): array
    {
        return $opportunity->productLines()
            ->get(['business_function_id', 'product_category_id'])
            ->map(static fn ($line): array => [
                'business_function_id' => (int) $line->business_function_id,
                'product_category_id' => (int) $line->product_category_id,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function normalize(array $rows): array
    {
        // Null-coalesced: a malformed row must still produce a comparable
        // pair, so the diff below can hand it to the validator instead of
        // failing on a missing key.
        return array_map(static fn (array $row): array => [
            'business_function_id' => (int) ($row['business_function_id'] ?? 0),
            'product_category_id' => (int) ($row['product_category_id'] ?? 0),
        ], array_values($rows));
    }

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $pairs
     * @return array<int, string>
     */
    private function pairKeys(array $pairs): array
    {
        $keys = array_map(
            static fn (array $pair): string => "{$pair['business_function_id']}:{$pair['product_category_id']}",
            $pairs,
        );
        sort($keys);

        return $keys;
    }
}
